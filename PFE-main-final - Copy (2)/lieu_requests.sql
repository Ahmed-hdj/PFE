-- Create the lieu_requests table
CREATE TABLE lieu_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_name VARCHAR(100),
    request_date DATE,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_date TIMESTAMP NULL,
    rejected_date TIMESTAMP NULL
); 