create table student_records(
    name varchar(50) NOT NULL,
    admission_no varchar(15) NOT NULL PRIMARY KEY,
    birth_cert varchar(20),
    dob date,
    gender ENUM('male', 'female') NOT NULL,
    class ENUM('babyclass','intermediate','PP1','PP2','grade1','grade2','grade3','grade4','grade5','grade6') NOT NULL,
    term ENUM('term1','term2','term3') NOT NULL,
    religion ENUM('christian','muslim','other') NOT NULL,
    gurdian varchar(20) NOT NULL,
    phone int(13) NOT NULL,
    student_photo LONGBLOB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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

    CREATE TABLE school_fees(
    admission_no varchar(15) NOT NULL PRIMARY KEY,
    birth_cert varchar(20),
   class ENUM('babyclass','intermediate','PP1','PP2','grade1','grade2','grade3','grade4','grade5','grade6') NOT NULL,
    term ENUM('term1','term2','term3') NOT NULL,
    total_fee DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    balance DECIMAL(10, 2) 
    );

    CREATE TABLE lunch_fees (
    id INT AUTO_INCREMENT PRIMARY KEY,            -- Unique ID for each record
    admission_no VARCHAR(15) NOT NULL,            -- Student admission number
    birth_cert VARCHAR(20),                       -- Birth certificate number (optional)
    total_amount DECIMAL(10, 2) DEFAULT 350.00,   -- Total fee for the week (default 350)
    total_paid DECIMAL(10, 2) NOT NULL DEFAULT 0.00,  -- Total amount paid by the student
    balance DECIMAL(10, 2) NOT NULL DEFAULT 350.00,  -- Remaining balance (calculated as total_amount - total_paid)
    week_number INT NOT NULL,                     -- Week number (indicating the current week for tracking)
    monday DECIMAL(10, 2) NOT NULL DEFAULT 0.00,   -- Payment made for Monday
    tuesday DECIMAL(10, 2) NOT NULL DEFAULT 0.00,  -- Payment made for Tuesday
    wednesday DECIMAL(10, 2) NOT NULL DEFAULT 0.00, -- Payment made for Wednesday
    thursday DECIMAL(10, 2) NOT NULL DEFAULT 0.00,  -- Payment made for Thursday
    friday DECIMAL(10, 2) NOT NULL DEFAULT 0.00,   -- Payment made for Friday
    receipt_number VARCHAR(255)                   -- Unique receipt number for each week's payment
);
