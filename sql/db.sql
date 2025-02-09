-- administrator records start here
CREATE TABLE administration (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO administration(username,password,email) VALUES
('patricia','pasha2020','pasha2020@gmail.com'),
('Emmaculate',SHA2('Emma2020', 256),'immah2008@gmail.com'),
('admin','admin2020','jonesmusyoki.jm@gmail.com');
--administrator details end here


-- student records start here
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
-- student records end here


-- teacher records start here
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
-- teacher records end here

-- school fee details start here
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
--- school fee records end here


-- lunch fee records start here
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- lunch fee records end here


-- books records start here
CREATE TABLE Books (
    book_id INT PRIMARY KEY AUTO_INCREMENT,
    book_name VARCHAR(50) NOT NULL,
    category ENUM('Diary', 'Assessment Book') NOT NULL,
    price DECIMAL(10,2) NOT NULL
);

--book purchases
CREATE TABLE Book_Purchases (
    purchase_id INT PRIMARY KEY AUTO_INCREMENT,
    receipt_no VARCHAR(20) UNIQUE NOT NULL,
    admission_no VARCHAR(15) NOT NULL,
    name VARCHAR(100) NOT NULL,
    class ENUM('babyclass','intermediate','PP1','PP2','grade1','grade2','grade3','grade4','grade5','grade6') NOT NULL,
    book_id INT NOT NULL,
    book_name VARCHAR(50) NOT NULL,
    quantity INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES Books(book_id)
);

INSERT INTO Books (name, category, price) VALUES
('School Diary', 'Diary', 300.00),
('Assessment Book', 'Assessment Book', 250.00);
-- books records end here

-- school uniform
CREATE TABLE uniform_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uniform_type ENUM('Normal', 'PE') NOT NULL,
    size VARCHAR(10) NOT NULL,
    price DECIMAL(10,2) NOT NULL
);

CREATE TABLE uniform_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(20) UNIQUE NOT NULL,
    purchase_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_type ENUM('Cash', 'mpesa', 'bank_transfer') NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_id) REFERENCES uniform_purchases(id)
);


CREATE TABLE uniform_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    admission_no VARCHAR(20) NOT NULL,
    uniform_id INT NOT NULL,
    quantity INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
    balance DECIMAL(10,2) NOT NULL DEFAULT 0,
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uniform_id) REFERENCES uniform_prices(id)
);