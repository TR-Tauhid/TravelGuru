CREATE DATABASE IF NOT EXISTS bus_ticket;

USE bus_ticket;

CREATE TABLE IF NOT EXISTS passenger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    number VARCHAR(20) NOT NULL,
    selectSitArr TEXT NOT NULL,
    journey_date DATE,
    totalPrice DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
