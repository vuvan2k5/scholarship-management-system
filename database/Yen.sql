--Bảng này lưu trữ thông tin chi tiết về học vấn và hoàn cảnh xã hội của mỗi sinh viên.
-- Populating 50 student profiles based on the users created previously
INSERT INTO student_profiles (student_id, faculty, major, gpa, activities_count, family_income, is_disadvantaged)
SELECT 
    id, 
    CASE WHEN id % 3 = 0 THEN 'Information Technology' WHEN id % 3 = 1 THEN 'Business Administration' ELSE 'Foreign Languages' END,
    CASE WHEN id % 3 = 0 THEN 'Software Engineering' WHEN id % 3 = 1 THEN 'Digital Marketing' ELSE 'English Studies' END,
    ROUND(2.5 + (RAND() * 1.5), 2), -- Generates GPA between 2.5 and 4.0
    FLOOR(RAND() * 15),             -- Random activity count 0-15
    5000000 + (RAND() * 25000000),  -- Monthly income in VND
    CASE WHEN id % 5 = 0 THEN 1 ELSE 0 END -- 20% of students marked as disadvantaged
FROM users 
WHERE role = 'student' LIMIT 50;

--Thông tin này ghi lại sinh viên nào đang nộp đơn xin học bổng chương trình nào.
-- Creating 30 sample applications
INSERT INTO applications (student_id, program_id, status, submitted_at)
SELECT 
    id, 
    (id % 5) + 1, -- Distributes applications across 5 programs
    'submitted', 
    NOW()
FROM users 
WHERE role = 'student' AND id <= 30;
-- Đồng bộ dữ liệu mẫu với evaluation_scores
UPDATE applications
SET program_id = 1
WHERE id = 1;

UPDATE applications
SET program_id = 2
WHERE id = 2;
--Đây là chức năng cốt lõi của hệ thống . Nó lưu trữ điểm số thô do Hội đồng/Người đánh giá chấm cho từng tiêu chí cụ thể.
-- Example: Scoring for Application ID 1 (Academic Excellence Scholarship)
-- Assuming Reviewer IDs are 52 and 53 based on your previous insert
INSERT INTO evaluation_scores (application_id, criteria_id, council_id, score) VALUES
(1, 1, 52, 95), -- Reviewer One scores GPA criterion
(1, 2, 52, 80), -- Reviewer One scores Research criterion
(1, 3, 53, 85), -- Reviewer Two scores Activities criterion
(1, 4, 53, 70); -- Reviewer Two scores Financial Condition criterion

-- Example: Scoring for Application ID 2 (Research Innovation)
INSERT INTO evaluation_scores (application_id, criteria_id, council_id, score) VALUES
(2, 5, 52, 88),
(2, 6, 52, 92),
(2, 7, 53, 75);
--Bảng này giúp sinh viên cập nhật tiến độ xét duyệt hồ sơ của mình.
-- System generated notifications
INSERT INTO notifications (user_id, title, message, type, is_read)
VALUES 
(1, 'Application Received', 'Your application for the Academic Excellence Scholarship has been successfully submitted.', 'info', 0),
(2, 'Eligibility Passed', 'Congratulations! You have passed the initial eligibility check.', 'success', 0),
(3, 'Eligibility Failed', 'We regret to inform you that your GPA does not meet the minimum requirements.', 'error', 0);

