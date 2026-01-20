-- =====================================================
-- DATABASE INITIALIZATION SCRIPT
-- Movie Booking System - PostgreSQL
-- =====================================================

-- Create UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- =====================================================
-- USERS TABLE
-- =====================================================
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    email VARCHAR(255) UNIQUE NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    phone_number VARCHAR(20),
    role VARCHAR(20) DEFAULT 'customer' CHECK (role IN ('customer', 'admin', 'staff')),
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_username ON users(username);

-- =====================================================
-- MOVIES TABLE
-- =====================================================
CREATE TABLE movies (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    title VARCHAR(255) NOT NULL,
    description TEXT,
    duration_minutes INTEGER NOT NULL,
    genre VARCHAR(100),
    language VARCHAR(50),
    rating VARCHAR(10),
    release_date DATE,
    poster_url VARCHAR(500),
    trailer_url VARCHAR(500),
    director VARCHAR(255),
    cast TEXT,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_movies_title ON movies(title);
CREATE INDEX idx_movies_release_date ON movies(release_date);

-- =====================================================
-- THEATERS TABLE
-- =====================================================
CREATE TABLE theaters (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name VARCHAR(255) NOT NULL,
    location VARCHAR(500),
    city VARCHAR(100),
    total_seats INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- SHOWTIMES TABLE
-- =====================================================
CREATE TABLE showtimes (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    movie_id UUID NOT NULL REFERENCES movies(id) ON DELETE CASCADE,
    theater_id UUID NOT NULL REFERENCES theaters(id) ON DELETE CASCADE,
    show_date DATE NOT NULL,
    show_time TIME NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    available_seats INTEGER NOT NULL,
    total_seats INTEGER NOT NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_showtimes_movie ON showtimes(movie_id);
CREATE INDEX idx_showtimes_theater ON showtimes(theater_id);
CREATE INDEX idx_showtimes_date ON showtimes(show_date);

-- =====================================================
-- SEATS TABLE
-- =====================================================
CREATE TABLE seats (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    theater_id UUID NOT NULL REFERENCES theaters(id) ON DELETE CASCADE,
    seat_row VARCHAR(5) NOT NULL,
    seat_number INTEGER NOT NULL,
    seat_type VARCHAR(20) DEFAULT 'regular' CHECK (seat_type IN ('regular', 'vip', 'premium')),
    UNIQUE(theater_id, seat_row, seat_number)
);

CREATE INDEX idx_seats_theater ON seats(theater_id);

-- =====================================================
-- BOOKINGS TABLE
-- =====================================================
CREATE TABLE bookings (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    showtime_id UUID NOT NULL REFERENCES showtimes(id) ON DELETE CASCADE,
    booking_code VARCHAR(20) UNIQUE NOT NULL,
    total_seats INTEGER NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'confirmed', 'cancelled', 'completed', 'expired')),
    payment_status VARCHAR(20) DEFAULT 'unpaid' CHECK (payment_status IN ('unpaid', 'paid', 'refunded')),
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_bookings_user ON bookings(user_id);
CREATE INDEX idx_bookings_showtime ON bookings(showtime_id);
CREATE INDEX idx_bookings_code ON bookings(booking_code);
CREATE INDEX idx_bookings_status ON bookings(status);
CREATE INDEX idx_bookings_expires_at ON bookings(expires_at) WHERE status = 'pending';

-- =====================================================
-- BOOKING_SEATS TABLE (Junction Table)
-- =====================================================
CREATE TABLE booking_seats (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    booking_id UUID NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    seat_id UUID NOT NULL REFERENCES seats(id) ON DELETE CASCADE,
    showtime_id UUID NOT NULL REFERENCES showtimes(id) ON DELETE CASCADE,
    status VARCHAR(20) DEFAULT 'reserved' CHECK (status IN ('reserved', 'booked', 'cancelled', 'expired')),
    UNIQUE(showtime_id, seat_id)
);

CREATE INDEX idx_booking_seats_booking ON booking_seats(booking_id);
CREATE INDEX idx_booking_seats_showtime_seat ON booking_seats(showtime_id, seat_id);

-- =====================================================
-- PAYMENTS TABLE
-- =====================================================
CREATE TABLE payments (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    booking_id UUID NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50),
    transaction_id VARCHAR(255) UNIQUE,
    idempotency_key VARCHAR(255) UNIQUE,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'completed', 'failed', 'refunded')),
    payment_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_payments_booking ON payments(booking_id);
CREATE INDEX idx_payments_transaction ON payments(transaction_id);
CREATE INDEX idx_payments_idempotency ON payments(idempotency_key);

-- =====================================================
-- NOTIFICATIONS TABLE
-- =====================================================
CREATE TABLE notifications (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL CHECK (type IN ('email', 'sms', 'push')),
    subject VARCHAR(255),
    message TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'sent', 'failed')),
    sent_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_notifications_user ON notifications(user_id);
CREATE INDEX idx_notifications_status ON notifications(status);

-- =====================================================
-- TRIGGER: Update updated_at timestamp
-- =====================================================
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Apply trigger to all relevant tables
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_movies_updated_at BEFORE UPDATE ON movies
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_theaters_updated_at BEFORE UPDATE ON theaters
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_showtimes_updated_at BEFORE UPDATE ON showtimes
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_bookings_updated_at BEFORE UPDATE ON bookings
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_payments_updated_at BEFORE UPDATE ON payments
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- =====================================================
-- SEED DATA: Sample Admin User
-- =====================================================
-- Password: admin123 (hashed with bcrypt)
INSERT INTO users (email, username, password_hash, full_name, role) VALUES
('admin@moviebooking.com', 'admin', '$2b$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5GyYPvFzYN8Rm', 'System Administrator', 'admin');

-- Sample Movies
INSERT INTO movies (title, description, duration_minutes, genre, language, rating, release_date, director) VALUES
('The Matrix Resurrections', 'Neo returns to the Matrix', 148, 'Sci-Fi', 'English', 'PG-13', '2024-01-15', 'Lana Wachowski'),
('Dune: Part Two', 'Epic continuation of the Dune saga', 166, 'Sci-Fi', 'English', 'PG-13', '2024-03-01', 'Denis Villeneuve'),
('Avatar 3', 'The journey continues on Pandora', 180, 'Sci-Fi', 'English', 'PG-13', '2024-12-20', 'James Cameron');

-- Sample Theater
INSERT INTO theaters (name, location, city, total_seats) VALUES
('CGV Vincom Center', '72 Le Thanh Ton, District 1', 'Ho Chi Minh City', 150),
('Lotte Cinema Diamond Plaza', '34 Le Duan, District 1', 'Ho Chi Minh City', 200);

-- Sample Showtimes (Get theater and movie IDs from inserted records)
INSERT INTO showtimes (movie_id, theater_id, show_date, show_time, price, available_seats, total_seats)
SELECT 
    m.id,
    t.id,
    CURRENT_DATE + (CASE WHEN row_number() OVER () % 3 = 0 THEN 0 WHEN row_number() OVER () % 3 = 1 THEN 1 ELSE 2 END),
    (ARRAY['10:00', '14:00', '18:00', '21:00'])[((row_number() OVER () - 1) % 4) + 1]::TIME,
    (CASE 
        WHEN (row_number() OVER () % 4) + 1 <= 2 THEN 80000.00  -- Morning/afternoon shows
        ELSE 120000.00  -- Evening shows
    END),
    t.total_seats,
    t.total_seats
FROM 
    (SELECT id FROM movies LIMIT 2) m,
    (SELECT id, total_seats FROM theaters) t;

-- Sample Seats for Theater 1
INSERT INTO seats (theater_id, seat_row, seat_number, seat_type)
SELECT 
    t.id,
    chr(64 + ((n-1) / 10 + 1)),  -- A, B, C, D...
    (n-1) % 10 + 1,                -- 1-10
    CASE 
        WHEN ((n-1) / 10 + 1) <= 2 THEN 'vip'
        WHEN ((n-1) / 10 + 1) <= 5 THEN 'premium'
        ELSE 'regular'
    END
FROM 
    (SELECT id FROM theaters LIMIT 1) t,
    generate_series(1, 150) n;

-- Sample Seats for Theater 2
INSERT INTO seats (theater_id, seat_row, seat_number, seat_type)
SELECT 
    t.id,
    chr(64 + ((n-1) / 10 + 1)),
    (n-1) % 10 + 1,
    CASE 
        WHEN ((n-1) / 10 + 1) <= 3 THEN 'vip'
        WHEN ((n-1) / 10 + 1) <= 8 THEN 'premium'
        ELSE 'regular'
    END
FROM 
    (SELECT id FROM theaters OFFSET 1 LIMIT 1) t,
    generate_series(1, 200) n;

COMMIT;
