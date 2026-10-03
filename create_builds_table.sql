CREATE TABLE IF NOT EXISTS builds (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    build_name VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    components_text TEXT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    category ENUM('gaming','office','students') NOT NULL,
    is_recommended TINYINT(1) NOT NULL DEFAULT 0,
    rating DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_build_name_category (build_name, category),
    KEY idx_category_recommended (category, is_recommended),
    KEY idx_rating_created (rating, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO builds (build_name, description, components_text, price, category, is_recommended, rating) VALUES
('Gaming Beast Ryzen 5 + RTX 4060', 'High FPS 1080p/1440p gaming setup.', 'CPU: Ryzen 5 5600\nMotherboard: B550M\nRAM: 16GB DDR4\nGPU: RTX 4060\nStorage: 1TB NVMe SSD\nPSU: 650W 80+ Bronze', 64999.00, 'gaming', 1, 4.80),
('Gaming Value i5 + RTX 3050', 'Balanced gaming build for esports and AAA titles.', 'CPU: Intel Core i5-12400F\nMotherboard: B660M\nRAM: 16GB DDR4\nGPU: RTX 3050\nStorage: 500GB NVMe SSD\nPSU: 550W 80+ Bronze', 48999.00, 'gaming', 1, 4.60),
('Office Pro i3 + 16GB RAM', 'Fast office productivity for multitasking and meetings.', 'CPU: Intel Core i3-12100\nMotherboard: H610M\nRAM: 16GB DDR4\nGPU: Integrated Graphics\nStorage: 500GB SATA SSD\nPSU: 500W 80+ Bronze', 25999.00, 'office', 1, 4.50),
('Office Basic Ryzen 3 + SSD', 'Affordable office build for docs, browser, and email.', 'CPU: Ryzen 3 4100\nMotherboard: A520M\nRAM: 8GB DDR4\nGPU: Integrated Graphics\nStorage: 500GB SATA SSD\nPSU: 450W 80+ Bronze', 19999.00, 'office', 1, 4.30),
('Student Smart Ryzen 5 APU', 'Great for school projects, coding, and light editing.', 'CPU: Ryzen 5 5600G\nMotherboard: B550M\nRAM: 16GB DDR4\nGPU: Integrated Radeon Graphics\nStorage: 512GB NVMe SSD\nPSU: 500W 80+ Bronze', 29999.00, 'students', 1, 4.70),
('Student Saver i3 + SSD', 'Budget-friendly build for online classes and reports.', 'CPU: Intel Core i3-12100\nMotherboard: H610M\nRAM: 8GB DDR4\nGPU: Integrated UHD Graphics\nStorage: 256GB NVMe SSD\nPSU: 450W 80+ Bronze', 21999.00, 'students', 1, 4.40)
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    components_text = VALUES(components_text),
    price = VALUES(price),
    category = VALUES(category),
    is_recommended = VALUES(is_recommended),
    rating = VALUES(rating);
