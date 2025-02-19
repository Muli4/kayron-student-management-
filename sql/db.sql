
CREATE TABLE administration (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO administration(username,password,email) VALUES
('patricia',SHA2('pasha2020', 256),'pasha2020@gmail.com'),
('Emmaculate',SHA2('Emma2020', 256),'immah2008@gmail.com'),
('admin',SHA2('admin2020', 256),'jonesmusyoki.jm@gmail.com');



create table student_records(
    admission_no varchar(15) NOT NULL PRIMARY KEY,
    birth_cert varchar(20),
    name varchar(50) NOT NULL,
    dob date,
    gender ENUM('male', 'female') NOT NULL,
    student_photo LONGBLOB,
    class ENUM('babyclass','intermediate','PP1','PP2','grade1','grade2','grade3','grade4','grade5','grade6') NOT NULL,
    term ENUM('term1','term2','term3') NOT NULL,
    religion ENUM('christian','muslim','other') NOT NULL,
    gurdian varchar(20) NOT NULL,
    phone int(13) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
    id INT AUTO_INCREMENT PRIMARY KEY,
    admission_no VARCHAR(255) NOT NULL,
    total_paid DECIMAL(10, 2) DEFAULT 0,
    balance DECIMAL(10, 2) DEFAULT 350,
    total_amount DECIMAL(10, 2) DEFAULT 350,
    week_number INT NOT NULL,
    monday DECIMAL(10, 2) DEFAULT 0,
    tuesday DECIMAL(10, 2) DEFAULT 0,
    wednesday DECIMAL(10, 2) DEFAULT 0,
    thursday DECIMAL(10, 2) DEFAULT 0,
    friday DECIMAL(10, 2) DEFAULT 0,
    payment_type ENUM('Cash', 'mpesa', 'bank_transfer') NOT NULL
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
    term ENUM('term1', 'term2', 'term3') DEFAULT NULL,
    fee_type ENUM('Admission', 'Activity', 'Exam', 'Interview') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_type ENUM('Cash', 'mpesa', 'bank_transfer') NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_recurring BOOLEAN NOT NULL DEFAULT FALSE,
    INDEX (admission_no)
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
    uniform_type ENUM('Uniform', 'P.E T-Shirt', 'Track Suit', 'Track Suit') NOT NULL,
    size VARCHAR(50) NOT NULL,
    price DECIMAL(10,2) NOT NULL
);

CREATE TABLE uniform_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    admission_no VARCHAR(50) NOT NULL,
    uniform_type ENUM('Uniform', 'P.E T-Shirt', 'Track Suit', 'Track Suit') NOT NULL,
    size VARCHAR(50) NOT NULL,
    quantity INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    balance DECIMAL(10,2) NOT NULL,
    payment_type ENUM('Cash', 'Card', 'Bank Transfer', 'Other') NOT NULL DEFAULT 'Cash',
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


INSERT INTO uniform_prices (uniform_type, size, price) VALUES
('Uniform', 'All Sizes', 1000.00),
('P.E T-Shirt', 'All Sizes', 450.00),
('Track Suit', '20-26', 1800.00),
('Track Suit', '28-32', 2000.00);





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

