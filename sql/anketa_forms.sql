-- Generic, admin-configurable survey ("anketa") forms.
-- Separate from the legacy `vysledky_ankety_25` table, which stays untouched.

CREATE TABLE IF NOT EXISTS anketa_forms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    songbook_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    include_basic_questions TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (songbook_id) REFERENCES songbooks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS anketa_form_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    category_id INT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_form_category (form_id, category_id),
    FOREIGN KEY (form_id) REFERENCES anketa_forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS anketa_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    respondent VARCHAR(255) NOT NULL,
    respondent_key VARCHAR(255) NOT NULL,
    category_id INT NOT NULL,
    answers TEXT NOT NULL,
    is_authenticated TINYINT(1) NOT NULL DEFAULT 0,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_response (form_id, respondent_key, category_id),
    FOREIGN KEY (form_id) REFERENCES anketa_forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- If anketa_forms was already created before include_basic_questions existed,
-- run this once to add it:
-- ALTER TABLE anketa_forms ADD COLUMN include_basic_questions TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;
