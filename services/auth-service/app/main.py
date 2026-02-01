import logging
import os
import uuid
import uuid as uuid_pkg
from contextvars import ContextVar
from datetime import datetime, timedelta
from typing import Optional

import redis.asyncio as redis
from fastapi import Depends, FastAPI, HTTPException, Request, status
from fastapi.middleware.cors import CORSMiddleware
from fastapi.security import OAuth2PasswordBearer, OAuth2PasswordRequestForm
from jose import JWTError, jwt
from passlib.context import CryptContext
from prometheus_client import Counter, Histogram
from prometheus_fastapi_instrumentator import Instrumentator
from pydantic import BaseModel, EmailStr
from sqlalchemy import Boolean, Column, DateTime, String
from sqlalchemy.dialects.postgresql import UUID
from sqlalchemy.ext.asyncio import AsyncSession, async_sessionmaker, create_async_engine
from sqlalchemy.orm import declarative_base
from starlette.middleware.base import BaseHTTPMiddleware

# Correlation ID context
correlation_id_ctx: ContextVar[str] = ContextVar("correlation_id", default="")


# Configure logging with correlation ID
class CorrelationIdFilter(logging.Filter):
    def filter(self, record):
        record.correlation_id = correlation_id_ctx.get("")
        return True


# Basic logging config (without correlation_id to avoid external lib crashes)
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s - %(name)s - %(levelname)s - %(message)s",
)
logger = logging.getLogger(__name__)
logger.addFilter(CorrelationIdFilter())

# Custom metrics
login_counter = Counter("auth_logins_total", "Total login attempts", ["status"])
register_counter = Counter(
    "auth_registrations_total", "Total registration attempts", ["status"]
)
token_verify_histogram = Histogram(
    "auth_token_verify_seconds", "Token verification duration"
)

# =====================================================
# CONFIGURATION
# =====================================================
SECRET_KEY = os.getenv("SECRET_KEY", "your-super-secret-jwt-key-change-in-production")
ALGORITHM = os.getenv("ALGORITHM", "HS256")
ACCESS_TOKEN_EXPIRE_MINUTES = int(os.getenv("ACCESS_TOKEN_EXPIRE_MINUTES", "30"))
DATABASE_URL = os.getenv(
    "DATABASE_URL", "postgresql+asyncpg://admin:admin123@postgres:5432/movie_booking"
)
REDIS_URL = os.getenv("REDIS_URL", "redis://:redis123@redis:6379/0")
CORS_ORIGINS = os.getenv("CORS_ORIGINS", "http://localhost:8080,http://localhost:3000").split(",")

# =====================================================
# DATABASE SETUP
# =====================================================
engine = create_async_engine(DATABASE_URL, echo=True)
async_session_maker = async_sessionmaker(
    engine, class_=AsyncSession, expire_on_commit=False
)
Base = declarative_base()


# =====================================================
# MODELS
# =====================================================
class User(Base):
    __tablename__ = "users"

    id = Column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    email = Column(String(255), unique=True, nullable=False, index=True)
    username = Column(String(100), unique=True, nullable=False, index=True)
    password_hash = Column(String(255), nullable=False)
    full_name = Column(String(255))
    phone_number = Column(String(20))
    role = Column(String(20), default="customer")
    is_active = Column(Boolean, default=True)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)


# =====================================================
# PYDANTIC SCHEMAS
# =====================================================
class UserCreate(BaseModel):
    email: EmailStr
    username: str
    password: str
    full_name: Optional[str] = None
    phone_number: Optional[str] = None


class UserResponse(BaseModel):
    id: uuid.UUID
    email: str
    username: str
    full_name: Optional[str]
    role: str
    is_active: bool

    class Config:
        from_attributes = True


class Token(BaseModel):
    access_token: str
    token_type: str
    user: UserResponse


class TokenData(BaseModel):
    username: Optional[str] = None


# =====================================================
# SECURITY
# =====================================================
pwd_context = CryptContext(schemes=["bcrypt"], deprecated="auto")
oauth2_scheme = OAuth2PasswordBearer(tokenUrl="token")


def verify_password(plain_password: str, hashed_password: str) -> bool:
    return pwd_context.verify(plain_password, hashed_password)


def get_password_hash(password: str) -> str:
    return pwd_context.hash(password)


def create_access_token(data: dict, expires_delta: Optional[timedelta] = None):
    to_encode = data.copy()
    if expires_delta:
        expire = datetime.utcnow() + expires_delta
    else:
        expire = datetime.utcnow() + timedelta(minutes=15)
    to_encode.update({"exp": expire})
    encoded_jwt = jwt.encode(to_encode, SECRET_KEY, algorithm=ALGORITHM)
    return encoded_jwt


# =====================================================
# FASTAPI APP
# =====================================================
app = FastAPI(
    title="Auth Service",
    description="""
## Authentication & User Management Service

This service handles all authentication and user management operations for the Movie Booking System.

### Features:
- 🔐 **User Registration** - Create new user accounts
- 🔑 **User Login** - JWT-based authentication
- ✅ **Token Verification** - Validate access tokens
- 👤 **User Profile** - Get and update user information

### Authentication:
All protected endpoints require a valid JWT token in the Authorization header:
```
Authorization: Bearer <access_token>
```
    """,
    version="1.0.0",
    contact={"name": "Movie Booking Team", "email": "support@moviebooking.com"},
    license_info={"name": "MIT License"},
    openapi_tags=[
        {"name": "Authentication", "description": "Login, Register, Token operations"},
        {"name": "Users", "description": "User profile management"},
        {"name": "Health", "description": "Service health checks"},
    ],
)


# Correlation ID Middleware
class CorrelationIdMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request: Request, call_next):
        correlation_id = request.headers.get("X-Correlation-ID", str(uuid_pkg.uuid4()))
        correlation_id_ctx.set(correlation_id)
        logger.info(f"Request started: {request.method} {request.url.path}")
        response = await call_next(request)
        response.headers["X-Correlation-ID"] = correlation_id
        logger.info(f"Request completed: {response.status_code}")
        return response


app.add_middleware(CorrelationIdMiddleware)

# Prometheus instrumentation
Instrumentator().instrument(app).expose(app)

# CORS Middleware - Use environment variable for allowed origins
app.add_middleware(
    CORSMiddleware,
    allow_origins=CORS_ORIGINS,
    allow_credentials=True,
    allow_methods=["GET", "POST", "PUT", "DELETE", "OPTIONS", "PATCH"],
    allow_headers=["Authorization", "Content-Type", "X-Correlation-ID"],
)

# Redis Client
redis_client: Optional[redis.Redis] = None


@app.on_event("startup")
async def startup_event():
    global redis_client
    redis_client = await redis.from_url(REDIS_URL, decode_responses=True)

    # Create tables if not exist
    async with engine.begin() as conn:
        await conn.run_sync(Base.metadata.create_all)


@app.on_event("shutdown")
async def shutdown_event():
    if redis_client:
        await redis_client.close()


# =====================================================
# DEPENDENCY
# =====================================================
async def get_db():
    async with async_session_maker() as session:
        yield session


# =====================================================
# ROUTES
# =====================================================
@app.get("/")
async def root():
    return {"service": "Auth Service", "status": "running", "version": "1.0.0"}


@app.get("/health")
async def health_check():
    return {"status": "healthy", "timestamp": datetime.utcnow().isoformat()}


@app.post("/register", response_model=UserResponse, status_code=status.HTTP_201_CREATED)
async def register(user_data: UserCreate, db: AsyncSession = Depends(get_db)):
    """Register a new user"""
    from sqlalchemy import select

    try:
        # Check if user exists
        result = await db.execute(select(User).filter(User.email == user_data.email))
        if result.scalar_one_or_none():
            register_counter.labels(status="email_exists").inc()
            raise HTTPException(status_code=400, detail="Email already registered")

        result = await db.execute(
            select(User).filter(User.username == user_data.username)
        )
        if result.scalar_one_or_none():
            register_counter.labels(status="username_exists").inc()
            raise HTTPException(status_code=400, detail="Username already taken")

        # Create new user
        hashed_password = get_password_hash(user_data.password)
        new_user = User(
            email=user_data.email,
            username=user_data.username,
            password_hash=hashed_password,
            full_name=user_data.full_name,
            phone_number=user_data.phone_number,
        )

        db.add(new_user)
        await db.commit()
        await db.refresh(new_user)

        register_counter.labels(status="success").inc()
        logger.info(f"New user registered: {user_data.username}")
        return new_user
    except HTTPException:
        raise
    except Exception as e:
        register_counter.labels(status="error").inc()
        logger.error(f"Registration error: {e}")
        raise HTTPException(status_code=500, detail="Registration failed")


@app.post("/token", response_model=Token)
async def login(
    form_data: OAuth2PasswordRequestForm = Depends(), db: AsyncSession = Depends(get_db)
):
    """Login and get access token"""
    from sqlalchemy import select

    # Get user
    result = await db.execute(select(User).filter(User.username == form_data.username))
    user = result.scalar_one_or_none()

    if not user or not verify_password(form_data.password, user.password_hash):
        login_counter.labels(status="failed").inc()
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Incorrect username or password",
            headers={"WWW-Authenticate": "Bearer"},
        )

    if not user.is_active:
        login_counter.labels(status="inactive").inc()
        raise HTTPException(status_code=400, detail="Inactive user")

    # Create access token
    access_token_expires = timedelta(minutes=ACCESS_TOKEN_EXPIRE_MINUTES)
    access_token = create_access_token(
        data={"sub": user.username, "user_id": str(user.id), "role": user.role},
        expires_delta=access_token_expires,
    )

    # Cache user session in Redis
    if redis_client:
        await redis_client.setex(
            f"session:{user.id}", ACCESS_TOKEN_EXPIRE_MINUTES * 60, access_token
        )

    login_counter.labels(status="success").inc()
    logger.info(f"User logged in: {user.username}")

    return {"access_token": access_token, "token_type": "bearer", "user": user}


@app.get("/verify")
async def verify_token(
    token: str = Depends(oauth2_scheme), db: AsyncSession = Depends(get_db)
):
    """Verify JWT token and return user info"""
    import time

    from sqlalchemy import select

    start_time = time.time()

    credentials_exception = HTTPException(
        status_code=status.HTTP_401_UNAUTHORIZED,
        detail="Could not validate credentials",
        headers={"WWW-Authenticate": "Bearer"},
    )

    try:
        payload = jwt.decode(token, SECRET_KEY, algorithms=[ALGORITHM])
        username: str = payload.get("sub")
        if username is None:
            raise credentials_exception
    except JWTError:
        raise credentials_exception

    result = await db.execute(select(User).filter(User.username == username))
    user = result.scalar_one_or_none()

    if user is None:
        raise credentials_exception

    token_verify_histogram.observe(time.time() - start_time)

    return {
        "user_id": str(user.id),
        "username": user.username,
        "email": user.email,
        "full_name": user.full_name,
        "phone_number": user.phone_number,
        "role": user.role,
        "is_active": user.is_active,
    }


# =====================================================
# PROFILE UPDATE SCHEMA
# =====================================================
class ProfileUpdate(BaseModel):
    full_name: Optional[str] = None
    phone_number: Optional[str] = None


class PasswordChange(BaseModel):
    current_password: str
    new_password: str


@app.put("/profile", response_model=UserResponse)
async def update_profile(
    profile_data: ProfileUpdate,
    token: str = Depends(oauth2_scheme),
    db: AsyncSession = Depends(get_db),
):
    """Update user profile"""
    from sqlalchemy import select, update

    try:
        payload = jwt.decode(token, SECRET_KEY, algorithms=[ALGORITHM])
        user_id = payload.get("user_id")
        if not user_id:
            raise HTTPException(status_code=401, detail="Invalid token")
    except JWTError:
        raise HTTPException(status_code=401, detail="Invalid token")

    # Get user
    result = await db.execute(select(User).filter(User.id == uuid.UUID(user_id)))
    user = result.scalar_one_or_none()

    if not user:
        raise HTTPException(status_code=404, detail="User not found")

    # Update fields
    update_data = {}
    if profile_data.full_name is not None:
        update_data["full_name"] = profile_data.full_name
    if profile_data.phone_number is not None:
        update_data["phone_number"] = profile_data.phone_number

    if update_data:
        update_data["updated_at"] = datetime.utcnow()
        await db.execute(
            update(User).where(User.id == user.id).values(**update_data)
        )
        await db.commit()
        await db.refresh(user)

    logger.info(f"Profile updated for user: {user.username}")
    return user


@app.post("/change-password")
async def change_password(
    password_data: PasswordChange,
    token: str = Depends(oauth2_scheme),
    db: AsyncSession = Depends(get_db),
):
    """Change user password"""
    from sqlalchemy import select, update

    try:
        payload = jwt.decode(token, SECRET_KEY, algorithms=[ALGORITHM])
        user_id = payload.get("user_id")
        if not user_id:
            raise HTTPException(status_code=401, detail="Invalid token")
    except JWTError:
        raise HTTPException(status_code=401, detail="Invalid token")

    # Get user
    result = await db.execute(select(User).filter(User.id == uuid.UUID(user_id)))
    user = result.scalar_one_or_none()

    if not user:
        raise HTTPException(status_code=404, detail="User not found")

    # Verify current password
    if not verify_password(password_data.current_password, user.password_hash):
        raise HTTPException(status_code=400, detail="Current password is incorrect")

    # Validate new password
    if len(password_data.new_password) < 6:
        raise HTTPException(
            status_code=400, detail="New password must be at least 6 characters"
        )

    # Update password
    new_hash = get_password_hash(password_data.new_password)
    await db.execute(
        update(User)
        .where(User.id == user.id)
        .values(password_hash=new_hash, updated_at=datetime.utcnow())
    )
    await db.commit()

    # Invalidate all sessions
    if redis_client:
        await redis_client.delete(f"session:{user.id}")

    logger.info(f"Password changed for user: {user.username}")
    return {"message": "Password changed successfully"}


@app.post("/logout")
async def logout(token: str = Depends(oauth2_scheme)):
    """Logout user (invalidate token in Redis)"""
    try:
        payload = jwt.decode(token, SECRET_KEY, algorithms=[ALGORITHM])
        user_id = payload.get("user_id")

        if redis_client and user_id:
            await redis_client.delete(f"session:{user_id}")

        return {"message": "Successfully logged out"}
    except JWTError:
        raise HTTPException(status_code=401, detail="Invalid token")


if __name__ == "__main__":
    import uvicorn

    uvicorn.run(app, host="0.0.0.0", port=8000)
