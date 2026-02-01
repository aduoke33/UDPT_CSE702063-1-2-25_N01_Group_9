-- =====================================================
-- SEED DATA: Sample Movies for Testing
-- Movie Booking System - CineBook
-- =====================================================

-- Clear existing movies if needed (optional - comment out if you want to keep existing data)
-- DELETE FROM showtimes;
-- DELETE FROM movies;

-- =====================================================
-- PHIM ĐANG CHIẾU (NOW SHOWING)
-- =====================================================

INSERT INTO movies (title, description, duration_minutes, genre, language, rating, release_date, poster_url, trailer_url, director, cast, is_active) VALUES
(
    'Đào, Phở và Piano',
    'Bộ phim tái hiện cuộc sống của người dân Hà Nội trong những ngày cuối cùng trước khi tiếp quản thủ đô năm 1954. Câu chuyện xoay quanh một chiến sĩ tự vệ thành, một cô gái Hà Nội và những người dân bình thường trong bối cảnh chiến tranh.',
    128,
    'Tình cảm, Lịch sử',
    'Tiếng Việt',
    'P',
    '2024-01-15',
    'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?w=300&h=450&fit=crop',
    'https://www.youtube.com/watch?v=example1',
    'Phi Tiến Sơn',
    'Doãn Quốc Đam, Cao Thái Hà, NSƯT Trần Lực, Anh Tuấn',
    true
),
(
    'Mai',
    'Câu chuyện tình yêu đầy cảm xúc giữa Mai - một cô gái massage có quá khứ đau buồn và Dương - chàng trai Sài Gòn hào hoa. Bộ phim là hành trình tìm kiếm hạnh phúc và sự cứu rỗi của những tâm hồn tổn thương.',
    130,
    'Tình cảm, Tâm lý',
    'Tiếng Việt',
    'C18',
    '2024-02-10',
    'https://images.unsplash.com/photo-1536440136628-849c177e76a1?w=300&h=450&fit=crop',
    'https://www.youtube.com/watch?v=example2',
    'Trấn Thành',
    'Phương Anh Đào, Tuấn Trần, NSND Việt Anh, Hồng Đào',
    true
),
(
    'Godzilla x Kong: Đế Chế Mới',
    'Hai titan huyền thoại Godzilla và Kong phải hợp sức chống lại mối đe dọa chưa từng có từ sâu trong lòng đất. Cuộc phiêu lưu sử thi đưa họ vào cuộc chiến sinh tồn với những sinh vật khổng lồ chưa từng được biết đến.',
    115,
    'Hành động, Khoa học viễn tưởng',
    'Tiếng Anh',
    'C13',
    '2024-03-28',
    'https://images.unsplash.com/photo-1598899134739-24c46f58b8c0?w=300&h=450&fit=crop',
    'https://www.youtube.com/watch?v=example3',
    'Adam Wingard',
    'Rebecca Hall, Brian Tyree Henry, Dan Stevens, Kaylee Hottle',
    true
),
(
    'Kung Fu Panda 4',
    'Po được chọn làm Lãnh đạo Tinh thần của Thung lũng Hòa bình và phải tìm kiếm và huấn luyện Chiến binh Rồng mới. Trong khi đó, một phù thủy quỷ quyệt đang lên kế hoạch triệu hồi tất cả các kẻ thù cũ của Po.',
    94,
    'Hoạt hình, Hài hước',
    'Lồng tiếng',
    'P',
    '2024-03-08',
    'https://images.unsplash.com/photo-1440404653325-ab127d49abc1?w=300&h=450&fit=crop',
    'https://www.youtube.com/watch?v=example4',
    'Mike Mitchell',
    'Jack Black, Awkwafina, Viola Davis, Dustin Hoffman',
    true
),
(
    'Dune: Hành Tinh Cát - Phần Hai',
    'Paul Atreides hợp tác với người Fremen để báo thù những kẻ đã hủy diệt gia đình anh. Khi phải đối mặt với sự lựa chọn giữa tình yêu cuộc đời và số phận của vũ trụ, anh phải ngăn chặn một tương lai khủng khiếp mà chỉ mình anh có thể thấy trước.',
    166,
    'Khoa học viễn tưởng, Phiêu lưu',
    'Tiếng Anh',
    'C13',
    '2024-03-01',
    'https://images.unsplash.com/photo-1534447677768-be436bb09401?w=300&h=450&fit=crop',
    'https://www.youtube.com/watch?v=example5',
    'Denis Villeneuve',
    'Timothée Chalamet, Zendaya, Rebecca Ferguson, Austin Butler',
    true
),
(
    'Lật Mặt 7: Một Điều Ước',
    'Phần tiếp theo của series Lật Mặt, kể về câu chuyện gia đình xúc động với những biến cố bất ngờ. Bộ phim mang thông điệp về tình thân và những hy sinh thầm lặng của cha mẹ dành cho con cái.',
    132,
    'Gia đình, Tâm lý',
    'Tiếng Việt',
    'P',
    '2024-04-26',
    'https://images.unsplash.com/photo-1517604931442-7e0c8ed2963c?w=300&h=450&fit=crop',
    'https://www.youtube.com/watch?v=example6',
    'Lý Hải',
    'Lý Hải, Minh Hà, Quốc Cường, Huỳnh Đông',
    true
),
(
    'Quỷ Cẩu',
    'Bộ phim kinh dị Việt Nam lấy cảm hứng từ truyền thuyết dân gian về loài quỷ chó. Một gia đình bỗng nhiên phải đối mặt với những hiện tượng siêu nhiên đáng sợ khi chuyển về ngôi nhà cũ ở quê.',
    100,
    'Kinh dị',
    'Tiếng Việt',
    'C18',
    '2024-02-23',
    'https://images.unsplash.com/photo-1509347528160-9a9e33742cdb?w=300&h=450&fit=crop',
    'https://www.youtube.com/watch?v=example7',
    'Lưu Thành Luân',
    'Quang Tuấn, Đinh Y Nhung, Mạc Can, Hồng Ánh',
    true
),
(
    'Despicable Me 4',
    'Gru và Lucy chào đón thành viên mới của gia đình - Gru Jr. Trong khi đó, một kẻ thù mới xuất hiện buộc cả gia đình phải bỏ trốn và các Minions cũng có những biến đổi bất ngờ.',
    95,
    'Hoạt hình, Hài hước',
    'Lồng tiếng',
    'P',
    '2024-07-03',
    'https://images.unsplash.com/photo-1485846234645-a62644f84728?w=300&h=450&fit=crop',
    'https://www.youtube.com/watch?v=example8',
    'Chris Renaud',
    'Steve Carell, Kristen Wiig, Pierre Coffin, Will Ferrell',
    true
);

-- =====================================================
-- PHIM SẮP CHIẾU (COMING SOON)
-- =====================================================

INSERT INTO movies (title, description, duration_minutes, genre, language, rating, release_date, poster_url, trailer_url, director, cast, is_active) VALUES
(
    'Venom: The Last Dance',
    'Eddie Brock và Venom đang chạy trốn. Bị cả hai thế giới săn đuổi, họ buộc phải đưa ra quyết định tàn khốc sẽ hạ màn cho vở kịch cuối cùng của họ.',
    120,
    'Hành động, Khoa học viễn tưởng',
    'Tiếng Anh',
    'C13',
    '2024-10-25',
    'https://images.unsplash.com/photo-1635805737707-575885ab0820?w=300&h=450&fit=crop',
    'https://www.youtube.com/watch?v=example9',
    'Kelly Marcel',
    'Tom Hardy, Chiwetel Ejiofor, Juno Temple, Rhys Ifans',
    true
),
(
    'Joker: Folie à Deux',
    'Arthur Fleck đang chờ xét xử trong Arkham State Hospital. Khi đang vật lộn với hai bản ngã của mình, Arthur gặp tình yêu và cùng nhau tìm thấy âm nhạc vốn luôn ẩn sâu trong họ.',
    138,
    'Tâm lý, Nhạc kịch',
    'Tiếng Anh',
    'C18',
    '2024-10-04',
    'https://images.unsplash.com/photo-1559583985-c80d8ad9b29f?w=300&h=450&fit=crop',
    'https://www.youtube.com/watch?v=example10',
    'Todd Phillips',
    'Joaquin Phoenix, Lady Gaga, Brendan Gleeson, Catherine Keener',
    true
),
(
    'Gladiator II',
    'Lucius sống cuộc đời bình thường ở biên giới đế chế La Mã cho đến khi quân đội xâm lược, buộc anh phải chiến đấu trên đấu trường Colosseum. Số phận của Rome nằm trong tay anh.',
    150,
    'Hành động, Lịch sử',
    'Tiếng Anh',
    'C16',
    '2024-11-22',
    'https://images.unsplash.com/photo-1578662996442-48f60103fc96?w=300&h=450&fit=crop',
    'https://www.youtube.com/watch?v=example11',
    'Ridley Scott',
    'Paul Mescal, Denzel Washington, Pedro Pascal, Connie Nielsen',
    true
),
(
    'Wicked',
    'Câu chuyện về hai phù thủy nổi tiếng nhất xứ Oz - Elphaba với làn da xanh và Glinda xinh đẹp. Từ khi gặp nhau ở Đại học Shiz, tình bạn không ngờ của họ đã thay đổi cả hai mãi mãi.',
    160,
    'Nhạc kịch, Giả tưởng',
    'Tiếng Anh',
    'P',
    '2024-11-27',
    'https://images.unsplash.com/photo-1518676590629-3dcbd9c5a5c9?w=300&h=450&fit=crop',
    'https://www.youtube.com/watch?v=example12',
    'Jon M. Chu',
    'Cynthia Erivo, Ariana Grande, Jonathan Bailey, Michelle Yeoh',
    true
),
(
    'Mufasa: The Lion King',
    'Câu chuyện về nguồn gốc của Mufasa - từ một chú sư tử mồ côi trở thành vị vua huyền thoại. Rafiki kể cho Kiara về quá khứ phi thường của ông nội cô.',
    118,
    'Hoạt hình, Phiêu lưu',
    'Lồng tiếng',
    'P',
    '2024-12-20',
    'https://images.unsplash.com/photo-1494976388531-d1058494cdd8?w=300&h=450&fit=crop',
    'https://www.youtube.com/watch?v=example13',
    'Barry Jenkins',
    'Aaron Pierre, Kelvin Harrison Jr., John Kani, Seth Rogen',
    true
),
(
    'Moana 2',
    'Moana nhận được cuộc gọi bất ngờ từ tổ tiên và phải đi đến vùng biển xa xôi của Châu Đại Dương cùng với một đoàn thủy thủ mới để thực hiện sứ mệnh nguy hiểm.',
    100,
    'Hoạt hình, Phiêu lưu',
    'Lồng tiếng',
    'P',
    '2024-11-27',
    'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=300&h=450&fit=crop',
    'https://www.youtube.com/watch?v=example14',
    'David G. Derrick Jr.',
    'Auli''i Cravalho, Dwayne Johnson, Rachel House, Temuera Morrison',
    true
);

-- =====================================================
-- ADD SHOWTIMES FOR ALL MOVIES
-- =====================================================

-- Add showtimes for today and next 7 days
INSERT INTO showtimes (movie_id, theater_id, show_date, show_time, price, available_seats, total_seats)
SELECT 
    m.id,
    t.id,
    CURRENT_DATE + d.day_offset,
    show_times.show_time::TIME,
    CASE 
        WHEN show_times.show_time IN ('10:00', '14:00') THEN 75000.00
        WHEN show_times.show_time IN ('17:00', '19:00') THEN 95000.00
        ELSE 120000.00
    END,
    t.total_seats,
    t.total_seats
FROM 
    movies m
CROSS JOIN 
    theaters t
CROSS JOIN 
    (VALUES (0), (1), (2), (3), (4), (5), (6)) AS d(day_offset)
CROSS JOIN 
    (VALUES ('10:00'), ('14:00'), ('17:00'), ('19:00'), ('21:30')) AS show_times(show_time)
WHERE 
    m.release_date <= CURRENT_DATE + d.day_offset
    AND m.is_active = true
ORDER BY 
    m.id, t.id, d.day_offset, show_times.show_time;

-- =====================================================
-- SUMMARY
-- =====================================================
-- Total movies: 14 (8 now showing + 6 coming soon)
-- Showtimes: Automatically generated for each movie at each theater
-- Shows: 5 showtimes per day (10:00, 14:00, 17:00, 19:00, 21:30)
-- Duration: 7 days of showtimes

SELECT 'Movies seeded successfully!' AS status;
SELECT COUNT(*) AS total_movies FROM movies;
SELECT COUNT(*) AS total_showtimes FROM showtimes;
