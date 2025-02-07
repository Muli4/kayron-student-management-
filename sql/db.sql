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
    id INT AUTO_INCREMENT PRIMARY KEY,                  -- Unique ID for each record
    admission_no VARCHAR(255) NOT NULL,                  -- Admission Number of the student
    total_paid DECIMAL(10, 2) DEFAULT 0,                 -- Total amount paid so far
    balance DECIMAL(10, 2) DEFAULT 350,                  -- Remaining balance for the week (initially 350)
    total_amount DECIMAL(10, 2) DEFAULT 350,             -- Total amount for the week (set to 350)
    week_number INT NOT NULL,                            -- Week number for the payment
    monday DECIMAL(10, 2) DEFAULT 0,                     -- Payment made on Monday
    tuesday DECIMAL(10, 2) DEFAULT 0,                    -- Payment made on Tuesday
    wednesday DECIMAL(10, 2) DEFAULT 0,                  -- Payment made on Wednesday
    thursday DECIMAL(10, 2) DEFAULT 0,                   -- Payment made on Thursday
    friday DECIMAL(10, 2) DEFAULT 0,                     -- Payment made on Friday
    payment_type ENUM('Cash', 'Liquid Money') NOT NULL  -- Type of payment (Cash or Liquid Money)
);

CREATE TABLE lunch_fee_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,               -- Unique ID for each transaction
    name varchar(50) NOT NULL,
    class ENUM('babyclass','intermediate','PP1','PP2','grade1','grade2','grade3','grade4','grade5','grade6') NOT NULL,
    admission_no VARCHAR(50) NOT NULL,                -- Admission number of the student
    amount_paid DECIMAL(10, 2) NOT NULL,              -- Amount paid in the transaction
    receipt_number VARCHAR(20) NOT NULL,              -- Unique receipt number
    payment_type ENUM('Cash', 'Liquid Money') NOT NULL  -- Type of payment (Cash or Liquid Money)
);

