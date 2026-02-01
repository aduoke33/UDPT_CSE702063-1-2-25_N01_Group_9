-- Insert showtimes for movies
-- Theater ID: 66f73d37-47d5-41d0-b9cf-ed0493bd4925

-- Showtimes for Joker: Folie a Deux
INSERT INTO showtimes (id, movie_id, theater_id, show_date, show_time, price, available_seats, total_seats, is_active, created_at, updated_at) VALUES
(gen_random_uuid(), 'f4137d4a-fd8e-4aad-913f-cc0d42b3c730', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '14:00:00', 120000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), 'f4137d4a-fd8e-4aad-913f-cc0d42b3c730', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '17:30:00', 150000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), 'f4137d4a-fd8e-4aad-913f-cc0d42b3c730', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '21:00:00', 150000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), 'f4137d4a-fd8e-4aad-913f-cc0d42b3c730', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE + 1, '10:00:00', 100000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), 'f4137d4a-fd8e-4aad-913f-cc0d42b3c730', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE + 1, '15:00:00', 120000.00, 100, 100, true, NOW(), NOW());

-- Showtimes for Venom: The Last Dance
INSERT INTO showtimes (id, movie_id, theater_id, show_date, show_time, price, available_seats, total_seats, is_active, created_at, updated_at) VALUES
(gen_random_uuid(), 'f9dacd53-e2dd-4c0d-abe7-6cd4e95ac5c1', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '13:00:00', 100000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), 'f9dacd53-e2dd-4c0d-abe7-6cd4e95ac5c1', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '16:00:00', 120000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), 'f9dacd53-e2dd-4c0d-abe7-6cd4e95ac5c1', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '19:30:00', 130000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), 'f9dacd53-e2dd-4c0d-abe7-6cd4e95ac5c1', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE + 1, '11:00:00', 100000.00, 100, 100, true, NOW(), NOW());

-- Showtimes for Despicable Me 4
INSERT INTO showtimes (id, movie_id, theater_id, show_date, show_time, price, available_seats, total_seats, is_active, created_at, updated_at) VALUES
(gen_random_uuid(), 'c2f1b489-a495-4895-b3c9-0815b9d87ce2', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '09:30:00', 80000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), 'c2f1b489-a495-4895-b3c9-0815b9d87ce2', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '12:00:00', 80000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), 'c2f1b489-a495-4895-b3c9-0815b9d87ce2', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '14:30:00', 90000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), 'c2f1b489-a495-4895-b3c9-0815b9d87ce2', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE + 1, '09:30:00', 80000.00, 100, 100, true, NOW(), NOW());

-- Showtimes for Quy Cau
INSERT INTO showtimes (id, movie_id, theater_id, show_date, show_time, price, available_seats, total_seats, is_active, created_at, updated_at) VALUES
(gen_random_uuid(), 'cbeea4e2-c6e3-40ba-8363-296669d6c307', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '20:00:00', 90000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), 'cbeea4e2-c6e3-40ba-8363-296669d6c307', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '22:30:00', 100000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), 'cbeea4e2-c6e3-40ba-8363-296669d6c307', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE + 1, '21:00:00', 100000.00, 100, 100, true, NOW(), NOW());

-- Showtimes for Lat Mat 7
INSERT INTO showtimes (id, movie_id, theater_id, show_date, show_time, price, available_seats, total_seats, is_active, created_at, updated_at) VALUES
(gen_random_uuid(), '511704b6-6cde-4a0b-8ec9-5f12b7664e8e', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '14:00:00', 100000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), '511704b6-6cde-4a0b-8ec9-5f12b7664e8e', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '18:00:00', 120000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), '511704b6-6cde-4a0b-8ec9-5f12b7664e8e', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE + 1, '13:00:00', 100000.00, 100, 100, true, NOW(), NOW());

-- Showtimes for Dune: Part Two
INSERT INTO showtimes (id, movie_id, theater_id, show_date, show_time, price, available_seats, total_seats, is_active, created_at, updated_at) VALUES
(gen_random_uuid(), '9d03f8ce-22e3-463e-bff0-f538b776ac15', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '13:30:00', 130000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), '9d03f8ce-22e3-463e-bff0-f538b776ac15', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '17:00:00', 150000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), '9d03f8ce-22e3-463e-bff0-f538b776ac15', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '20:30:00', 150000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), '9d03f8ce-22e3-463e-bff0-f538b776ac15', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE + 1, '14:00:00', 130000.00, 100, 100, true, NOW(), NOW());

-- Showtimes for Kung Fu Panda 4
INSERT INTO showtimes (id, movie_id, theater_id, show_date, show_time, price, available_seats, total_seats, is_active, created_at, updated_at) VALUES
(gen_random_uuid(), '1ccfc491-afa8-4c61-aa8c-131ea1ec1f06', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '10:00:00', 75000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), '1ccfc491-afa8-4c61-aa8c-131ea1ec1f06', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '12:30:00', 75000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), '1ccfc491-afa8-4c61-aa8c-131ea1ec1f06', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '15:00:00', 85000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), '1ccfc491-afa8-4c61-aa8c-131ea1ec1f06', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE + 1, '10:30:00', 75000.00, 100, 100, true, NOW(), NOW());

-- Showtimes for Godzilla x Kong
INSERT INTO showtimes (id, movie_id, theater_id, show_date, show_time, price, available_seats, total_seats, is_active, created_at, updated_at) VALUES
(gen_random_uuid(), '792d90c3-297f-4fb6-ac1e-129cf3790062', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '14:30:00', 110000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), '792d90c3-297f-4fb6-ac1e-129cf3790062', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '17:30:00', 130000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), '792d90c3-297f-4fb6-ac1e-129cf3790062', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '20:00:00', 130000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), '792d90c3-297f-4fb6-ac1e-129cf3790062', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE + 1, '15:30:00', 110000.00, 100, 100, true, NOW(), NOW());

-- Showtimes for Mai
INSERT INTO showtimes (id, movie_id, theater_id, show_date, show_time, price, available_seats, total_seats, is_active, created_at, updated_at) VALUES
(gen_random_uuid(), '30cbf380-13e0-476d-8b12-a27164a8c6fd', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '13:30:00', 95000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), '30cbf380-13e0-476d-8b12-a27164a8c6fd', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '16:30:00', 110000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), '30cbf380-13e0-476d-8b12-a27164a8c6fd', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '19:30:00', 110000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), '30cbf380-13e0-476d-8b12-a27164a8c6fd', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE + 1, '14:30:00', 95000.00, 100, 100, true, NOW(), NOW());

-- Showtimes for Dao Pho va Piano
INSERT INTO showtimes (id, movie_id, theater_id, show_date, show_time, price, available_seats, total_seats, is_active, created_at, updated_at) VALUES
(gen_random_uuid(), 'd9abb229-5e4a-42a8-8396-2a91c00aa8cc', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '15:00:00', 90000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), 'd9abb229-5e4a-42a8-8396-2a91c00aa8cc', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE, '18:30:00', 100000.00, 100, 100, true, NOW(), NOW()),
(gen_random_uuid(), 'd9abb229-5e4a-42a8-8396-2a91c00aa8cc', '66f73d37-47d5-41d0-b9cf-ed0493bd4925', CURRENT_DATE + 1, '11:00:00', 90000.00, 100, 100, true, NOW(), NOW());
