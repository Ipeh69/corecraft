CREATE TABLE IF NOT EXISTS build_recommendations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    build_name VARCHAR(120) NOT NULL,
    category VARCHAR(40) NOT NULL,
    description TEXT NOT NULL,
    cpu VARCHAR(160) NOT NULL,
    motherboard VARCHAR(160) NOT NULL,
    ram VARCHAR(160) NOT NULL,
    gpu VARCHAR(160) NOT NULL,
    storage VARCHAR(160) NOT NULL,
    psu VARCHAR(160) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    pros TEXT NULL,
    cons TEXT NULL,
    PRIMARY KEY (id),
    KEY idx_recommendation_category (category),
    KEY idx_recommendation_price (price)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE build_recommendations MODIFY COLUMN category VARCHAR(40) NOT NULL;

INSERT INTO build_recommendations
(build_name, category, description, price, pros, cons, cpu, motherboard, ram, gpu, storage, psu)
VALUES
('Gaming Value 1080p', 'gaming', 'Balanced entry gaming build for esports and modern games.', 28000, 'Dedicated GPU delivers smooth 1080p gaming; balanced parts keep the budget practical.', 'May need a GPU upgrade for high-refresh 1440p gaming.', 'Ryzen 5 5600 (boxed; stock cooler included)', 'B550M (AM4, DDR4)', '16GB DDR4', 'RX 6600 8GB', '1TB NVMe SSD', '550W 80+ Bronze'),
('Gaming Beast 1440p', 'gaming', 'High-performance build for demanding 1080p and 1440p gaming.', 65000, 'Strong CPU and GPU support high settings and better frame rates.', 'Higher power draw and cost than a basic gaming build.', 'Ryzen 5 7600 (boxed; stock cooler included)', 'B650 ATX (AM5, DDR5)', '32GB DDR5', 'RTX 4070 12GB', '1TB NVMe SSD', '750W 80+ Gold'),
('Student Starter', 'students', 'Practical PC for schoolwork, coding, research, and online classes.', 22000, 'Affordable, responsive, and efficient for school applications and multitasking.', 'Not suitable for demanding gaming or heavy video editing.', 'Ryzen 5 5600G (boxed; integrated Radeon graphics and stock cooler included)', 'B550M (AM4, DDR4)', '16GB DDR4', 'Integrated Radeon graphics (from CPU; no separate GPU)', '512GB NVMe SSD', '500W 80+ Bronze'),
('Office Productivity', 'office', 'Reliable system for documents, browser work, meetings, and productivity.', 26000, 'Fast startup and smooth everyday multitasking with low operating cost.', 'Integrated graphics limit gaming and GPU-heavy applications.', 'Intel Core i3-12100 (boxed; UHD 730 graphics and stock cooler included)', 'H610M (LGA1700, DDR4)', '16GB DDR4', 'Integrated UHD 730 graphics (from CPU; no separate GPU)', '500GB SATA SSD', '500W 80+ Bronze'),
('Starter Streaming', 'streaming', 'Affordable setup for livestreaming and esports content.', 30000, 'Dedicated GPU and capable CPU support entry-level streaming.', 'May struggle with demanding games and high-quality 4K streams.', 'Ryzen 5 5600 (boxed; stock cooler included)', 'B550M (AM4, DDR4)', '16GB DDR4', 'RTX 3060 12GB', '1TB NVMe SSD', '650W 80+ Bronze'),
('Creator Editing', 'editing', 'Editing-focused build for 1080p projects and content creation.', 42000, '32GB RAM and fast storage improve editing, previews, and exports.', 'Complex 4K effects and professional exports will take longer.', 'Ryzen 7 7700 (boxed; stock cooler included)', 'B650M (AM5, DDR5)', '32GB DDR5', 'RTX 4060 8GB', '2TB NVMe SSD', '650W 80+ Gold'),
('Developer Workstation', 'workstation', 'Powerful build for development, rendering, virtualization, and multitasking.', 55000, 'High core count and ample memory handle demanding professional workloads.', 'More expensive and power-hungry than needed for basic office use.', 'Ryzen 9 7900 (boxed; stock cooler included)', 'B650 ATX (AM5, DDR5)', '64GB DDR5', 'RTX 4070 12GB', '2TB NVMe SSD', '750W 80+ Gold'),
('Home Office Essential', 'home', 'Quiet everyday system for browsing, documents, calls, and media.', 20000, 'Low-cost, quiet, and easy to maintain for common home tasks.', 'Limited for AAA gaming, 3D work, and professional editing.', 'Intel Core i3-12100 (boxed; UHD 730 graphics and stock cooler included)', 'H610M (LGA1700, DDR4)', '16GB DDR4', 'Integrated UHD 730 graphics (from CPU; no separate GPU)', '500GB SATA SSD', '500W 80+ Bronze');

UPDATE build_recommendations SET
    cpu = CASE build_name
        WHEN 'Gaming Beast 1440p' THEN 'Ryzen 5 7600 (boxed; stock cooler included)'
        WHEN 'Office Productivity' THEN 'Intel Core i3-12100 (boxed; UHD 730 graphics and stock cooler included)'
        WHEN 'Student Starter' THEN 'Ryzen 5 5600G (boxed; integrated Radeon graphics and stock cooler included)'
        WHEN 'Gaming Value 1080p' THEN 'Ryzen 5 5600 (boxed; stock cooler included)'
        WHEN 'Starter Streaming' THEN 'Ryzen 5 5600 (boxed; stock cooler included)'
        WHEN 'Creator Editing' THEN 'Ryzen 7 7700 (boxed; stock cooler included)'
        WHEN 'Developer Workstation' THEN 'Ryzen 9 7900 (boxed; stock cooler included)'
        WHEN 'Home Office Essential' THEN 'Intel Core i3-12100 (boxed; UHD 730 graphics and stock cooler included)'
        ELSE cpu
    END,
    motherboard = CASE build_name
        WHEN 'Gaming Value 1080p' THEN 'B550M (AM4, DDR4)'
        WHEN 'Gaming Beast 1440p' THEN 'B650 ATX (AM5, DDR5)'
        WHEN 'Student Starter' THEN 'B550M (AM4, DDR4)'
        WHEN 'Office Productivity' THEN 'H610M (LGA1700, DDR4)'
        WHEN 'Starter Streaming' THEN 'B550M (AM4, DDR4)'
        WHEN 'Creator Editing' THEN 'B650M (AM5, DDR5)'
        WHEN 'Developer Workstation' THEN 'B650 ATX (AM5, DDR5)'
        WHEN 'Home Office Essential' THEN 'H610M (LGA1700, DDR4)'
        ELSE motherboard
    END,
    gpu = CASE build_name
        WHEN 'Student Starter' THEN 'Integrated Radeon graphics (from CPU; no separate GPU)'
        WHEN 'Office Productivity' THEN 'Integrated UHD 730 graphics (from CPU; no separate GPU)'
        WHEN 'Home Office Essential' THEN 'Integrated UHD 730 graphics (from CPU; no separate GPU)'
        ELSE gpu
    END
WHERE build_name IN ('Gaming Value 1080p', 'Gaming Beast 1440p', 'Student Starter', 'Office Productivity', 'Starter Streaming', 'Creator Editing', 'Developer Workstation', 'Home Office Essential');
