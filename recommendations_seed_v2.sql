-- Adds enough database-backed recommendations to reach five builds per category.
-- Estimated budgets are guidance only and should be checked against current local prices.
-- Safe to run repeatedly: existing category/build-name pairs are skipped.

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO build_recommendations
(build_name, category, description, price, pros, cons, cpu, motherboard, ram, gpu, storage, psu)
SELECT new_builds.build_name, new_builds.category, new_builds.description, new_builds.price,
       new_builds.pros, new_builds.cons, new_builds.cpu, new_builds.motherboard,
       new_builds.ram, new_builds.gpu, new_builds.storage, new_builds.psu
FROM (
    SELECT 'Gaming Balanced 1440p' AS build_name, 'gaming' AS category, 'Balanced 1440p gaming build with a strong midrange graphics card.' AS description, 48000 AS price, '32GB memory and a dedicated GPU provide a smooth 1440p starting point.' AS pros, 'Newer demanding games may need reduced settings for a steady high refresh rate.' AS cons, 'Ryzen 5 5600 (boxed; stock cooler included)' AS cpu, 'B550M (AM4, DDR4)' AS motherboard, '32GB DDR4' AS ram, 'RX 7600 8GB' AS gpu, '1TB NVMe SSD' AS storage, '650W 80+ Bronze' AS psu
    UNION ALL SELECT 'Gaming AM5 1080p', 'gaming', 'Modern AM5 gaming build for high-refresh 1080p play and future CPU upgrades.', 62000, 'AM5 platform and DDR5 memory leave room for future upgrades.', 'The RTX 4060 is aimed at 1080p and may need lower settings at 1440p.', 'Ryzen 5 7600 (boxed; stock cooler included)', 'B650M (AM5, DDR5)', '32GB DDR5', 'RTX 4060 8GB', '1TB NVMe SSD', '650W 80+ Gold'
    UNION ALL SELECT 'Gaming 4K Performance', 'gaming', 'High-end gaming build for demanding 1440p and entry 4K settings.', 138000, 'Powerful GPU and 12-core CPU suit demanding games and multitasking.', 'High-end parts cost more and need a well-ventilated case.', 'Ryzen 9 7900 (boxed; stock cooler included)', 'B650 ATX (AM5, DDR5)', '32GB DDR5', 'RTX 4080 16GB', '2TB NVMe SSD', '850W 80+ Gold'

    UNION ALL SELECT 'Office Compact Intel', 'office', 'Compact everyday office build for documents, browsing, and video calls.', 22500, 'Low power use and integrated graphics keep the build simple.', 'Not intended for modern gaming or GPU-heavy applications.', 'Intel Core i3-14100 (boxed; integrated UHD 730 graphics and stock cooler included)', 'H610M (LGA1700, DDR4)', '16GB DDR4', 'Integrated UHD 730 graphics (from CPU; no separate GPU)', '500GB SATA SSD', '500W 80+ Bronze'
    UNION ALL SELECT 'Office Multitasking', 'office', 'Responsive office system for heavy browser use, spreadsheets, and meetings.', 31000, 'Six CPU cores and 16GB memory handle everyday multitasking well.', 'Integrated graphics limit creative and gaming workloads.', 'Intel Core i5-12400 (boxed; integrated UHD 730 graphics and stock cooler included)', 'B660M (LGA1700, DDR4)', '16GB DDR4', 'Integrated UHD 730 graphics (from CPU; no separate GPU)', '1TB NVMe SSD', '550W 80+ Bronze'
    UNION ALL SELECT 'Office AMD APU', 'office', 'Quiet AMD office build with integrated graphics and roomy fast storage.', 29000, 'Integrated Radeon graphics and a six-core CPU cover common work tasks.', 'A discrete GPU is needed for demanding 3D or gaming work.', 'Ryzen 5 5600G (boxed; integrated Radeon graphics and stock cooler included)', 'B550M (AM4, DDR4)', '16GB DDR4', 'Integrated Radeon graphics (from CPU; no separate GPU)', '1TB NVMe SSD', '500W 80+ Bronze'
    UNION ALL SELECT 'Office Professional 32GB', 'office', 'Higher-capacity office PC for large spreadsheets and many simultaneous apps.', 45000, '32GB memory and a recent CPU support demanding office multitasking.', 'Integrated graphics are not suited to professional GPU rendering.', 'Intel Core i5-14400 (boxed; integrated UHD 730 graphics and stock cooler included)', 'B760M (LGA1700, DDR4)', '32GB DDR4', 'Integrated UHD 730 graphics (from CPU; no separate GPU)', '1TB NVMe SSD', '550W 80+ Bronze'

    UNION ALL SELECT 'Student Compact Study PC', 'students', 'Affordable study PC for research, online classes, and school documents.', 21500, 'Efficient parts cover school applications without a separate graphics card.', 'Integrated graphics are limited for gaming and 3D coursework.', 'Intel Core i3-12100 (boxed; UHD 730 graphics and stock cooler included)', 'H610M (LGA1700, DDR4)', '16GB DDR4', 'Integrated UHD 730 graphics (from CPU; no separate GPU)', '500GB SATA SSD', '500W 80+ Bronze'
    UNION ALL SELECT 'Student APU Study Plus', 'students', 'Flexible school and coding PC with integrated Radeon graphics.', 28000, 'Six CPU cores, 32GB memory, and fast storage suit coding and multitasking.', 'No discrete GPU for heavier CAD, 3D, or modern gaming.', 'Ryzen 5 5600G (boxed; integrated Radeon graphics and stock cooler included)', 'B550M (AM4, DDR4)', '32GB DDR4', 'Integrated Radeon graphics (from CPU; no separate GPU)', '1TB NVMe SSD', '550W 80+ Bronze'
    UNION ALL SELECT 'Student Coding Lab', 'students', 'School and programming build for IDEs, virtual classes, and multitasking.', 36000, 'Six CPU cores and 32GB RAM provide headroom for development tools.', 'Integrated graphics may limit GPU-accelerated engineering software.', 'Intel Core i5-12400 (boxed; integrated UHD 730 graphics and stock cooler included)', 'B660M (LGA1700, DDR4)', '32GB DDR4', 'Integrated UHD 730 graphics (from CPU; no separate GPU)', '1TB NVMe SSD', '550W 80+ Bronze'
    UNION ALL SELECT 'Student Creator PC', 'students', 'More capable build for design coursework, light editing, and gaming.', 62000, 'Dedicated RTX graphics and 32GB DDR5 support creative applications.', 'A larger GPU upgrade may be needed for advanced 3D or 4K work.', 'Ryzen 5 7600 (boxed; stock cooler included)', 'B650M (AM5, DDR5)', '32GB DDR5', 'RTX 4060 8GB', '1TB NVMe SSD', '650W 80+ Gold'

    UNION ALL SELECT 'Streaming Capture Starter', 'streaming', 'Starter streaming build for esports, capture, and 1080p broadcasts.', 43000, 'Dedicated NVIDIA graphics and 32GB RAM support entry-level streaming.', 'Very demanding games may require lower stream or game settings.', 'Ryzen 5 5600 (boxed; stock cooler included)', 'B550M (AM4, DDR4)', '32GB DDR4', 'RTX 3060 12GB', '1TB NVMe SSD', '650W 80+ Bronze'
    UNION ALL SELECT 'Streaming AM5 1080p', 'streaming', 'Modern streaming build for 1080p gaming and creator multitasking.', 63000, 'AM5 CPU, DDR5 memory, and NVIDIA encoder suit gaming streams.', 'The GPU is a midrange option for newer games at higher resolutions.', 'Ryzen 5 7600 (boxed; stock cooler included)', 'B650M (AM5, DDR5)', '32GB DDR5', 'RTX 4060 8GB', '1TB NVMe SSD', '650W 80+ Gold'
    UNION ALL SELECT 'Streaming 1440p Pro', 'streaming', 'Higher-performance streaming build for 1440p gaming and concurrent apps.', 88000, 'Eight-core CPU and RTX 4070 provide strong game and stream performance.', 'The build costs more and benefits from good case airflow.', 'Ryzen 7 7700 (boxed; stock cooler included)', 'B650 ATX (AM5, DDR5)', '32GB DDR5', 'RTX 4070 12GB', '2TB NVMe SSD', '750W 80+ Gold'
    UNION ALL SELECT 'Streaming Studio 4K', 'streaming', 'Premium streaming setup for demanding games and production workflows.', 132000, 'High core count, 64GB memory, and RTX 4080 suit heavier production.', 'High-end power draw and cost are unnecessary for basic 1080p streams.', 'Ryzen 9 7900 (boxed; stock cooler included)', 'B650 ATX (AM5, DDR5)', '64GB DDR5', 'RTX 4080 16GB', '2TB NVMe SSD', '850W 80+ Gold'

    UNION ALL SELECT 'Editing 4K Starter', 'editing', 'Balanced creator PC for 1080p and lighter 4K editing projects.', 62000, '32GB DDR5 and fast NVMe storage improve editing responsiveness.', 'Complex 4K effects may benefit from more memory and GPU power.', 'Ryzen 7 7700 (boxed; stock cooler included)', 'B650M (AM5, DDR5)', '32GB DDR5', 'RTX 4060 8GB', '2TB NVMe SSD', '650W 80+ Gold'
    UNION ALL SELECT 'Creator Multitask 64GB', 'editing', 'Editing build for larger timelines, multitasking, and frequent exports.', 82000, 'Twelve CPU cores and 64GB memory suit heavier editing sessions.', 'GPU memory may constrain complex effects and high-resolution projects.', 'Ryzen 9 7900 (boxed; stock cooler included)', 'B650 ATX (AM5, DDR5)', '64GB DDR5', 'RTX 4060 8GB', '2TB NVMe SSD', '750W 80+ Gold'
    UNION ALL SELECT 'Video Editing 4K Pro', 'editing', 'Powerful 4K editing build for larger projects and GPU-assisted effects.', 118000, 'Ryzen 9 CPU, 64GB memory, and RTX 4070 provide balanced creator performance.', 'Very complex effects may benefit from a higher VRAM GPU.', 'Ryzen 9 7900 (boxed; stock cooler included)', 'B650 ATX (AM5, DDR5)', '64GB DDR5', 'RTX 4070 12GB', '2TB NVMe SSD', '850W 80+ Gold'
    UNION ALL SELECT 'Studio Editing Workstation', 'editing', 'High-capacity workstation for demanding editing, rendering, and large media libraries.', 168000, '128GB memory, RTX 4080, and 4TB NVMe storage suit advanced workflows.', 'Premium parts and power requirements exceed typical hobby editing needs.', 'Ryzen 9 7900 (boxed; stock cooler included)', 'B650 ATX (AM5, DDR5)', '128GB DDR5', 'RTX 4080 16GB', '4TB NVMe SSD', '1000W 80+ Gold'

    UNION ALL SELECT 'Developer Multi-VM', 'workstation', 'Developer workstation for containers, virtual machines, and large builds.', 78000, 'Eight CPU cores and 64GB RAM provide room for parallel development tasks.', 'The midrange GPU is not intended for high-end rendering.', 'Ryzen 7 7700 (boxed; stock cooler included)', 'B650 ATX (AM5, DDR5)', '64GB DDR5', 'RTX 4060 8GB', '2TB NVMe SSD', '750W 80+ Gold'
    UNION ALL SELECT '3D Design Workstation', 'workstation', 'Workstation for 3D design, rendering, and demanding multitasking.', 105000, 'Twelve CPU cores, 64GB RAM, and RTX 4070 balance compute and graphics.', 'Very large scenes may need additional memory or GPU VRAM.', 'Ryzen 9 7900 (boxed; stock cooler included)', 'B650 ATX (AM5, DDR5)', '64GB DDR5', 'RTX 4070 12GB', '2TB NVMe SSD', '850W 80+ Gold'
    UNION ALL SELECT 'Render Workstation 128GB', 'workstation', 'High-memory workstation for large renders, virtual machines, and media work.', 165000, '128GB memory and RTX 4080 support demanding professional workloads.', 'High purchase cost and power draw are excessive for everyday computing.', 'Ryzen 9 7900 (boxed; stock cooler included)', 'B650 ATX (AM5, DDR5)', '128GB DDR5', 'RTX 4080 16GB', '4TB NVMe SSD', '1000W 80+ Gold'
    UNION ALL SELECT 'Compute Workstation 4K', 'workstation', 'Top-tier single-GPU workstation for intensive rendering and GPU compute.', 230000, 'RTX 4090 and 128GB RAM provide substantial GPU and memory capacity.', 'Requires a large, well-cooled case and a high-quality power supply.', 'Ryzen 9 7900 (boxed; stock cooler included)', 'B650 ATX (AM5, DDR5)', '128GB DDR5', 'RTX 4090 24GB', '4TB NVMe SSD', '1000W 80+ Gold'

    UNION ALL SELECT 'Home Office Family PC', 'home', 'Everyday family PC for browsing, remote work, documents, and video calls.', 21500, 'Low-cost integrated graphics and 16GB memory cover common household tasks.', 'Not designed for modern AAA gaming or GPU-heavy creation.', 'Intel Core i3-12100 (boxed; UHD 730 graphics and stock cooler included)', 'H610M (LGA1700, DDR4)', '16GB DDR4', 'Integrated UHD 730 graphics (from CPU; no separate GPU)', '500GB SATA SSD', '500W 80+ Bronze'
    UNION ALL SELECT 'Home Office AMD APU', 'home', 'Quiet home PC with integrated graphics and a fast 1TB drive.', 27000, 'Six-core CPU and integrated Radeon graphics handle common home use.', 'A discrete GPU is needed for demanding games and 3D tasks.', 'Ryzen 5 5600G (boxed; integrated Radeon graphics and stock cooler included)', 'B550M (AM4, DDR4)', '16GB DDR4', 'Integrated Radeon graphics (from CPU; no separate GPU)', '1TB NVMe SSD', '500W 80+ Bronze'
    UNION ALL SELECT 'Home Office Multitask', 'home', 'Comfortable system for remote work, many browser tabs, and family use.', 34000, '32GB RAM and a six-core CPU keep multitasking responsive.', 'Integrated graphics are limited for gaming and GPU-heavy applications.', 'Intel Core i5-12400 (boxed; integrated UHD 730 graphics and stock cooler included)', 'B660M (LGA1700, DDR4)', '32GB DDR4', 'Integrated UHD 730 graphics (from CPU; no separate GPU)', '1TB NVMe SSD', '550W 80+ Bronze'
    UNION ALL SELECT 'Home Office Premium 2TB', 'home', 'Premium everyday PC with ample memory and storage for a busy household.', 46000, '32GB memory and 2TB storage provide comfortable long-term everyday capacity.', 'The price is higher than needed for basic browsing and documents.', 'Ryzen 5 5600G (boxed; integrated Radeon graphics and stock cooler included)', 'B550M (AM4, DDR4)', '32GB DDR4', 'Integrated Radeon graphics (from CPU; no separate GPU)', '2TB NVMe SSD', '550W 80+ Bronze'
) AS new_builds
WHERE NOT EXISTS (
    SELECT 1
    FROM build_recommendations AS existing
    WHERE existing.category = new_builds.category
      AND existing.build_name = new_builds.build_name
);
