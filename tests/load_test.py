#!/usr/bin/env python3
"""
Load Testing Script for Movie Booking System
Uses locust for distributed load testing
"""

from locust import HttpUser, task, between
import random
import string
import json


def random_string(length=8):
    """Generate random string"""
    return ''.join(random.choices(string.ascii_lowercase + string.digits, k=length))


class MovieBookingUser(HttpUser):
    """Simulates user behavior on the movie booking system"""
    
    wait_time = between(1, 5)
    
    def on_start(self):
        """Setup - register and login"""
        self.username = f"loadtest_{random_string()}"
        self.email = f"{self.username}@test.com"
        self.password = "Test@123456"
        self.token = None
        self.user_id = None
        
        # Register
        response = self.client.post("/api/auth/register", json={
            "username": self.username,
            "email": self.email,
            "password": self.password,
            "full_name": f"Load Test User {self.username}"
        })
        
        if response.status_code == 200:
            data = response.json()
            self.user_id = data.get("id")
        
        # Login
        response = self.client.post("/api/auth/token", data={
            "username": self.username,
            "password": self.password
        })
        
        if response.status_code == 200:
            data = response.json()
            self.token = data.get("access_token")
    
    def _get_headers(self):
        """Get auth headers"""
        if self.token:
            return {"Authorization": f"Bearer {self.token}"}
        return {}
    
    @task(10)
    def browse_movies(self):
        """Browse movies - most common action"""
        self.client.get("/api/movies/movies")
    
    @task(8)
    def view_movie_details(self):
        """View specific movie details"""
        # Get movie list first
        response = self.client.get("/api/movies/movies")
        if response.status_code == 200:
            movies = response.json()
            if movies:
                movie_id = random.choice(movies).get("id")
                self.client.get(f"/api/movies/movies/{movie_id}")
    
    @task(6)
    def browse_showtimes(self):
        """Browse available showtimes"""
        self.client.get("/api/movies/showtimes")
    
    @task(4)
    def view_theaters(self):
        """View theater information"""
        self.client.get("/api/movies/theaters")
    
    @task(2)
    def verify_token(self):
        """Verify authentication token"""
        self.client.get("/api/auth/verify", headers=self._get_headers())
    
    @task(1)
    def create_booking(self):
        """Create a booking - less frequent"""
        if not self.token:
            return
            
        # Get available showtimes
        response = self.client.get("/api/movies/showtimes")
        if response.status_code != 200:
            return
            
        showtimes = response.json()
        if not showtimes:
            return
            
        showtime = random.choice(showtimes)
        
        # Create booking
        self.client.post(
            "/api/bookings/bookings",
            json={
                "showtime_id": showtime.get("id"),
                "seats": ["A1", "A2"]
            },
            headers=self._get_headers()
        )


class AdminUser(HttpUser):
    """Simulates admin user behavior"""
    
    wait_time = between(2, 10)
    weight = 1  # Lower weight - fewer admin users
    
    def on_start(self):
        """Admin login"""
        self.token = None
        
        response = self.client.post("/api/auth/token", data={
            "username": "admin",
            "password": "admin123"
        })
        
        if response.status_code == 200:
            data = response.json()
            self.token = data.get("access_token")
    
    def _get_headers(self):
        if self.token:
            return {"Authorization": f"Bearer {self.token}"}
        return {}
    
    @task(5)
    def view_all_bookings(self):
        """View all bookings"""
        self.client.get("/api/bookings/bookings", headers=self._get_headers())
    
    @task(3)
    def view_analytics(self):
        """View booking analytics"""
        self.client.get("/api/bookings/analytics", headers=self._get_headers())
    
    @task(2)
    def manage_movies(self):
        """View movie management"""
        self.client.get("/api/movies/movies", headers=self._get_headers())


class HealthCheckUser(HttpUser):
    """Periodically checks service health"""
    
    wait_time = between(5, 15)
    weight = 1
    
    @task
    def health_check_auth(self):
        self.client.get("/api/auth/health")
    
    @task
    def health_check_movies(self):
        self.client.get("/api/movies/health")
    
    @task
    def health_check_bookings(self):
        self.client.get("/api/bookings/health")
    
    @task
    def health_check_payments(self):
        self.client.get("/api/payments/health")
    
    @task
    def health_check_notifications(self):
        self.client.get("/api/notifications/health")


if __name__ == "__main__":
    import os
    os.system("locust -f load_test.py --host=http://localhost:80")
