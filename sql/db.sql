CREATE TABLE administration (
    id INT NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(50) NOT NULL,
    role ENUM('admin', 'teacher') NOT NULL DEFAULT 'teacher',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    login_attempts INT DEFAULT 0,
    is_locked TINYINT(1) DEFAULT 0,
    PRIMARY KEY (id)
);


INSERT INTO administration(username, password, email, role) VALUES
('patricia', SHA2('pasha2020', 256), 'pasha2020@gmail.com', 'admin'),
('Emmaculate', SHA2('Emma2020', 256), 'immah2008@gmail.com', 'admin'),
('admin', SHA2('admin2020', 256), 'jonesmusyoki.jm@gmail.com', 'admin'),
('maurice', SHA2('maurice123', 256),'mauricemuli730@gmail.com', 'admin');



CREATE TABLE student_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admission_no VARCHAR(20) NOT NULL UNIQUE,
    birth_cert VARCHAR(20),
    name VARCHAR(100) NOT NULL,
    dob DATE,
    gender ENUM('male', 'female') NOT NULL,
    student_photo LONGBLOB,
    class ENUM(
        'babyclass', 'intermediate', 'pp1', 'pp2',
        'grade1', 'grade2', 'grade3', 'grade4', 'grade5', 'grade6'
    ) NOT NULL,
    term ENUM('term1', 'term2', 'term3') NOT NULL,
    religion ENUM('christian', 'muslim', 'other'),
    guardian VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    alt_phone VARCHAR(20), -- <-- new field for alternate phone number
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);



CREATE TABLE graduated_students (
    admission_no VARCHAR(20) PRIMARY KEY,
    name VARCHAR(100),
    birth_certificate VARCHAR(100),
    dob DATE,
    gender VARCHAR(10),
    class_completed VARCHAR(50),
    term VARCHAR(20),
    religion VARCHAR(50),
    guardian_name VARCHAR(100),
    phone VARCHAR(20),
    student_photo VARCHAR(255),
    year_completed INT,
    graduation_date DATE
);


CREATE TABLE school_fees(
    admission_no varchar(15) NOT NULL PRIMARY KEY,
    birth_cert varchar(20),
    class ENUM('babyclass','intermediate','PP1','PP2','grade1','grade2','grade3','grade4','grade5','grade6') NOT NULL,
    term ENUM('term1','term2','term3') NOT NULL,
    total_fee DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    balance DECIMAL(10, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE school_fee_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(50) NOT NULL UNIQUE,
    admission_no VARCHAR(15) NOT NULL,
    name varchar(50) NOT NULL,
    class ENUM('babyclass','intermediate','PP1','PP2','grade1','grade2','grade3','grade4','grade5','grade6') NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_type VARCHAR(20) NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);




CREATE TABLE lunch_fees (
    id INT NOT NULL AUTO_INCREMENT,
    admission_no VARCHAR(255) NOT NULL,
    term_id INT DEFAULT NULL,
    total_paid DECIMAL(10,2) DEFAULT 0.00,
    balance DECIMAL(10,2) DEFAULT 350.00,
    total_amount DECIMAL(10,2) DEFAULT 350.00,
    week_number INT NOT NULL,
    monday DECIMAL(10,2) DEFAULT 0.00,
    tuesday DECIMAL(10,2) DEFAULT 0.00,
    wednesday DECIMAL(10,2) DEFAULT 0.00,
    thursday DECIMAL(10,2) DEFAULT 0.00,
    friday DECIMAL(10,2) DEFAULT 0.00,
    payment_type ENUM('Cash','mpesa','bank_transfer') NOT NULL,
    carry_forward DECIMAL(10,2) DEFAULT 0.00,
    PRIMARY KEY (id)
);

CREATE TABLE lunch_fee_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(20) NOT NULL,
    admission_no VARCHAR(50) NOT NULL,
    name varchar(50) NOT NULL,
    class ENUM('babyclass','intermediate','PP1','PP2','grade1','grade2','grade3','grade4','grade5','grade6') NOT NULL,
    amount_paid DECIMAL(10, 2) NOT NULL,
    payment_type ENUM('Cash', 'mpesa', 'bank_transfer') NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE others (
  id INT AUTO_INCREMENT PRIMARY KEY,
  receipt_number VARCHAR(50) NOT NULL,
  admission_no VARCHAR(50) NOT NULL,
  name VARCHAR(255) NOT NULL,
  term ENUM('term1','term2','term3') DEFAULT NULL,
  fee_type ENUM('Admission','Activity','Exam','Interview','Prize Giving','Graduation') NOT NULL,
  total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  balance DECIMAL(10,2) AS (total_amount - amount_paid) STORED NOT NULL,
  payment_type ENUM('Cash','mpesa','bank_transfer') NOT NULL,
  payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  is_recurring TINYINT(1) NOT NULL DEFAULT 0
);

CREATE TABLE other_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  others_id INT NOT NULL,
  amount_paid DECIMAL(10,2) NOT NULL,
  payment_type ENUM('Cash','mpesa','bank_transfer') NOT NULL,
  receipt_number VARCHAR(50) NOT NULL,
  transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status ENUM('Completed','Pending','Reversed') DEFAULT 'Completed',
  FOREIGN KEY (others_id) REFERENCES others(id) ON DELETE CASCADE
);



CREATE TABLE book_prices (
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    category ENUM('Diary', 'Assessment Book') NOT NULL,
    book_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL
);

CREATE TABLE  book_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(50) NOT NULL,
    admission_no VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    book_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    balance DECIMAL(10,2) NOT NULL DEFAULT 0,   
    payment_type ENUM('Cash', 'M-Pesa', 'Bank Transfer') NOT NULL,
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admission_no) REFERENCES student_records(admission_no) ON DELETE CASCADE
);

INSERT INTO book_prices (book_name, category, price) VALUES
('School Diary', 'Diary', 100.00),
('Assessment Book', 'Assessment Book', 250.00);



CREATE TABLE uniform_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uniform_type ENUM('Uniform', 'P.E T-Shirt', 'Track Suit') NOT NULL,
    size VARCHAR(50) NOT NULL,
    price DECIMAL(10,2) NOT NULL
);


CREATE TABLE uniform_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    admission_no VARCHAR(50) NOT NULL,
    uniform_type ENUM('Uniform', 'P.E T-Shirt', 'Track Suit') NOT NULL,
    size VARCHAR(50) NOT NULL,
    quantity INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    balance DECIMAL(10,2) NOT NULL,
    payment_type ENUM('Cash','Card','Bank Transfer','Other', 'M-Pesa') NOT NULL DEFAULT 'Cash',
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);



INSERT INTO uniform_prices (uniform_type, size, price) VALUES
('Uniform', 'All Sizes', 1000.00),
('P.E T-Shirt', 'All Sizes', 450.00),
('Track Suit', '20-26', 1800.00),
('Track Suit', '28-32', 2000.00);

CREATE TABLE purchase_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(50) NOT NULL,
    admission_no VARCHAR(50) NOT NULL,
    student_name VARCHAR(255) NOT NULL,
    total_amount_paid DECIMAL(10, 2) NOT NULL,
    payment_type VARCHAR(50) NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE teacher_records(
    id int(20) NOT NULL PRIMARY KEY,
    name varchar(20) NOT NULL,
    gender ENUM('male','female'),
    tsc varchar(20),
    code varchar(10) NOT NULL,
    employment_date date,
    status enum('active','inactive'),
    teacher_photo LONGBLOB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);



CREATE TABLE terms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    term_number INT NOT NULL CHECK (term_number BETWEEN 1 AND 3),
    year INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    UNIQUE KEY unique_term_year (term_number, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE weeks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    term_id INT NOT NULL,
    week_number INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (term_id, week_number),
    FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE
);

CREATE TABLE days (
    id INT AUTO_INCREMENT PRIMARY KEY,
    week_id INT NOT NULL,
    day_name ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday') NOT NULL,
    is_public_holiday BOOLEAN NOT NULL DEFAULT 0,
    UNIQUE(week_id, day_name),
    FOREIGN KEY (week_id) REFERENCES weeks(id) ON DELETE CASCADE
);

CREATE TABLE attendance (
    admission_no VARCHAR(20),
    term_number INT,
    week_number INT,
    day_name VARCHAR(10),
    status ENUM('Present', 'Absent'),
    PRIMARY KEY (admission_no, term_number, week_number, day_name)
);

