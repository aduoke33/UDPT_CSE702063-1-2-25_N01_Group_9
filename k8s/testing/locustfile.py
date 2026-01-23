# =====================================================
# LOCUST LOAD TESTING SCRIPT
# Movie Booking System - Performance Testing
# =====================================================

from locust import HttpUser, task, between, events
from locust.runners import MasterRunner
import random
import json
import logging
from datetime import datetime

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


class MovieBookingUser(HttpUser):
    """
    Simulates a typical user flow for the movie booking system.
    """
    
    # Wait between 1-5 seconds between tasks
    wait_time = between(1, 5)
    
    # Class-level token cache
    _token = None
    _user_id = None
    
    def on_start(self):
        """Called when a simulated user starts."""
        self.register_and_login()
    
    def register_and_login(self):
        """Register a new user and login to get auth token."""
        timestamp = datetime.now().strftime("%Y%m%d%H%M%S%f")
        email = f"user_{timestamp}_{random.randint(1000, 9999)}@test.com"
        
        # Try to register
        register_data = {
            "email": email,
            "password": "Test@123456",
            "name": f"Test User {timestamp}"
        }
        
        with self.client.post(
            "/api/v1/auth/register",
            json=register_data,
            catch_response=True
        ) as response:
            if response.status_code in [200, 201]:
                data = response.json()
                self._token = data.get("access_token")
                self._user_id = data.get("user_id")
                response.success()
            elif response.status_code == 409:  # User exists
                # Try login instead
                self._login(email, register_data["password"])
            else:
                response.failure(f"Registration failed: {response.status_code}")
    
    def _login(self, email, password):
        """Login with existing credentials."""
        login_data = {
            "email": email,
            "password": password
        }
        
        with self.client.post(
            "/api/v1/auth/login",
            json=login_data,
            catch_response=True
        ) as response:
            if response.status_code == 200:
                data = response.json()
                self._token = data.get("access_token")
                self._user_id = data.get("user_id")
                response.success()
            else:
                response.failure(f"Login failed: {response.status_code}")
    
    @property
    def auth_headers(self):
        """Get authorization headers."""
        if self._token:
            return {
                "Authorization": f"Bearer {self._token}",
                "Content-Type": "application/json"
            }
        return {"Content-Type": "application/json"}
    
    # =====================================================
    # BROWSING TASKS (High frequency - 60%)
    # =====================================================
    
    @task(30)
    def browse_movies(self):
        """Browse available movies - most common action."""
        with self.client.get(
            "/api/v1/movies/",
            headers=self.auth_headers,
            name="/api/v1/movies/ [GET]",
            catch_response=True
        ) as response:
            if response.status_code == 200:
                response.success()
            else:
                response.failure(f"Failed to get movies: {response.status_code}")
    
    @task(15)
    def get_movie_details(self):
        """Get details for a specific movie."""
        # First get list of movies
        movies_response = self.client.get(
            "/api/v1/movies/",
            headers=self.auth_headers
        )
        
        if movies_response.status_code == 200:
            movies = movies_response.json()
            if movies and len(movies) > 0:
                movie_id = random.choice(movies).get("id", 1)
                
                with self.client.get(
                    f"/api/v1/movies/{movie_id}",
                    headers=self.auth_headers,
                    name="/api/v1/movies/{id} [GET]",
                    catch_response=True
                ) as response:
                    if response.status_code in [200, 404]:
                        response.success()
                    else:
                        response.failure(f"Failed: {response.status_code}")
    
    @task(10)
    def get_showtimes(self):
        """Get showtimes for a movie."""
        movies_response = self.client.get(
            "/api/v1/movies/",
            headers=self.auth_headers
        )
        
        if movies_response.status_code == 200:
            movies = movies_response.json()
            if movies and len(movies) > 0:
                movie_id = random.choice(movies).get("id", 1)
                
                with self.client.get(
                    f"/api/v1/movies/{movie_id}/showtimes",
                    headers=self.auth_headers,
                    name="/api/v1/movies/{id}/showtimes [GET]",
                    catch_response=True
                ) as response:
                    if response.status_code in [200, 404]:
                        response.success()
                    else:
                        response.failure(f"Failed: {response.status_code}")
    
    @task(5)
    def search_movies(self):
        """Search for movies."""
        search_terms = ["action", "comedy", "drama", "thriller", "sci-fi"]
        term = random.choice(search_terms)
        
        with self.client.get(
            f"/api/v1/movies/search?q={term}",
            headers=self.auth_headers,
            name="/api/v1/movies/search [GET]",
            catch_response=True
        ) as response:
            if response.status_code in [200, 404]:
                response.success()
            else:
                response.failure(f"Search failed: {response.status_code}")
    
    # =====================================================
    # BOOKING TASKS (Medium frequency - 30%)
    # =====================================================
    
    @task(10)
    def view_my_bookings(self):
        """View user's existing bookings."""
        with self.client.get(
            "/api/v1/bookings/",
            headers=self.auth_headers,
            name="/api/v1/bookings/ [GET]",
            catch_response=True
        ) as response:
            if response.status_code in [200, 401]:
                response.success()
            else:
                response.failure(f"Failed: {response.status_code}")
    
    @task(15)
    def create_booking(self):
        """Create a new booking - critical business flow."""
        # Get movies first
        movies_response = self.client.get(
            "/api/v1/movies/",
            headers=self.auth_headers
        )
        
        if movies_response.status_code != 200:
            return
        
        movies = movies_response.json()
        if not movies or len(movies) == 0:
            return
        
        movie_id = random.choice(movies).get("id", 1)
        
        # Generate random seats
        rows = "ABCDEFGH"
        seats = [f"{random.choice(rows)}{random.randint(1, 10)}" for _ in range(random.randint(1, 4))]
        
        booking_data = {
            "movie_id": movie_id,
            "showtime_id": random.randint(1, 5),
            "seats": list(set(seats)),  # Remove duplicates
            "user_id": self._user_id
        }
        
        with self.client.post(
            "/api/v1/bookings/",
            json=booking_data,
            headers=self.auth_headers,
            name="/api/v1/bookings/ [POST]",
            catch_response=True
        ) as response:
            if response.status_code in [200, 201]:
                response.success()
                # Store booking for potential payment
                self._last_booking = response.json()
            elif response.status_code in [400, 401, 409]:
                # Expected failures (invalid data, unauthorized, seats taken)
                response.success()
            else:
                response.failure(f"Booking failed: {response.status_code}")
    
    @task(5)
    def cancel_booking(self):
        """Cancel an existing booking."""
        # Get user's bookings
        bookings_response = self.client.get(
            "/api/v1/bookings/",
            headers=self.auth_headers
        )
        
        if bookings_response.status_code == 200:
            bookings = bookings_response.json()
            if bookings and len(bookings) > 0:
                booking_id = random.choice(bookings).get("id")
                if booking_id:
                    with self.client.delete(
                        f"/api/v1/bookings/{booking_id}",
                        headers=self.auth_headers,
                        name="/api/v1/bookings/{id} [DELETE]",
                        catch_response=True
                    ) as response:
                        if response.status_code in [200, 204, 404]:
                            response.success()
                        else:
                            response.failure(f"Cancel failed: {response.status_code}")
    
    # =====================================================
    # PAYMENT TASKS (Low frequency - 10%)
    # =====================================================
    
    @task(8)
    def process_payment(self):
        """Process payment for a booking."""
        if not hasattr(self, '_last_booking') or not self._last_booking:
            return
        
        booking = self._last_booking
        payment_data = {
            "booking_id": booking.get("id"),
            "amount": booking.get("total_amount", 100.00),
            "payment_method": random.choice(["credit_card", "debit_card", "paypal"]),
            "card_number": "4111111111111111",
            "expiry_month": "12",
            "expiry_year": "2025",
            "cvv": "123"
        }
        
        with self.client.post(
            "/api/v1/payments/",
            json=payment_data,
            headers=self.auth_headers,
            name="/api/v1/payments/ [POST]",
            catch_response=True
        ) as response:
            if response.status_code in [200, 201]:
                response.success()
                self._last_booking = None  # Clear after payment
            elif response.status_code in [400, 401, 404]:
                response.success()
            else:
                response.failure(f"Payment failed: {response.status_code}")
    
    @task(2)
    def view_payment_history(self):
        """View payment history."""
        with self.client.get(
            "/api/v1/payments/history",
            headers=self.auth_headers,
            name="/api/v1/payments/history [GET]",
            catch_response=True
        ) as response:
            if response.status_code in [200, 401, 404]:
                response.success()
            else:
                response.failure(f"Failed: {response.status_code}")


class AdminUser(HttpUser):
    """
    Simulates admin operations (lower frequency).
    """
    
    wait_time = between(5, 15)
    weight = 1  # 1:10 ratio with regular users
    
    def on_start(self):
        """Admin login."""
        login_data = {
            "email": "admin@movie-booking.com",
            "password": "admin123"
        }
        
        response = self.client.post(
            "/api/v1/auth/login",
            json=login_data
        )
        
        if response.status_code == 200:
            self._token = response.json().get("access_token")
    
    @property
    def auth_headers(self):
        if hasattr(self, '_token') and self._token:
            return {
                "Authorization": f"Bearer {self._token}",
                "Content-Type": "application/json"
            }
        return {"Content-Type": "application/json"}
    
    @task(5)
    def view_all_bookings(self):
        """Admin view all bookings."""
        self.client.get(
            "/api/v1/bookings/admin/all",
            headers=self.auth_headers,
            name="/api/v1/bookings/admin/all [GET]"
        )
    
    @task(3)
    def view_statistics(self):
        """View system statistics."""
        self.client.get(
            "/api/v1/admin/statistics",
            headers=self.auth_headers,
            name="/api/v1/admin/statistics [GET]"
        )
    
    @task(2)
    def add_movie(self):
        """Add a new movie."""
        movie_data = {
            "title": f"Test Movie {random.randint(1000, 9999)}",
            "description": "A test movie for load testing",
            "duration": random.randint(90, 180),
            "genre": random.choice(["action", "comedy", "drama"]),
            "release_date": "2025-01-01"
        }
        
        self.client.post(
            "/api/v1/movies/",
            json=movie_data,
            headers=self.auth_headers,
            name="/api/v1/movies/ [POST]"
        )


# =====================================================
# EVENT HOOKS
# =====================================================

@events.test_start.add_listener
def on_test_start(environment, **kwargs):
    """Called when the test starts."""
    logger.info("=" * 60)
    logger.info("MOVIE BOOKING SYSTEM - LOAD TEST STARTED")
    logger.info(f"Target Host: {environment.host}")
    logger.info("=" * 60)


@events.test_stop.add_listener
def on_test_stop(environment, **kwargs):
    """Called when the test stops."""
    logger.info("=" * 60)
    logger.info("LOAD TEST COMPLETED")
    
    if environment.stats.total:
        stats = environment.stats.total
        logger.info(f"Total Requests: {stats.num_requests}")
        logger.info(f"Total Failures: {stats.num_failures}")
        logger.info(f"Failure Rate: {(stats.num_failures / stats.num_requests * 100) if stats.num_requests > 0 else 0:.2f}%")
        logger.info(f"Average Response Time: {stats.avg_response_time:.2f}ms")
        logger.info(f"Median Response Time: {stats.median_response_time:.2f}ms")
        logger.info(f"95th Percentile: {stats.get_response_time_percentile(0.95):.2f}ms")
        logger.info(f"99th Percentile: {stats.get_response_time_percentile(0.99):.2f}ms")
        logger.info(f"Requests/sec: {stats.total_rps:.2f}")
    
    logger.info("=" * 60)


@events.request.add_listener
def on_request(request_type, name, response_time, response_length, response, context, exception, **kwargs):
    """Called for every request."""
    if exception:
        logger.error(f"Request failed: {name} - {exception}")
    elif response and response.status_code >= 500:
        logger.warning(f"Server error: {name} - {response.status_code}")
