-- Repeatable seven-day check-in streak for PRO and Expert users.
CREATE TABLE IF NOT EXISTS pro_weekly_streak_cycles (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id INT UNSIGNED NOT NULL,
 cycle_number INT UNSIGNED NOT NULL,
 status ENUM('active','box_pending','completed','missed') NOT NULL DEFAULT 'active',
 current_day TINYINT UNSIGNED NOT NULL DEFAULT 0, started_on DATE NULL,
 last_checkin_on DATE NULL, box_reward TINYINT UNSIGNED NULL,
 box_unlocked_at DATETIME NULL, box_claimed_at DATETIME NULL,
 restart_available_at DATETIME NULL, ended_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id), UNIQUE KEY uq_pro_weekly_cycle_number(user_id,cycle_number),
 KEY idx_pro_weekly_cycle_state(user_id,status,id),
 CONSTRAINT fk_pro_weekly_cycle_user FOREIGN KEY(user_id) REFERENCES users(id)
  ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS pro_weekly_streak_checkins (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, cycle_id BIGINT UNSIGNED NOT NULL,
 user_id INT UNSIGNED NOT NULL, streak_day TINYINT UNSIGNED NOT NULL,
 checkin_date DATE NOT NULL, reward_amount DECIMAL(18,8) NOT NULL,
 ledger_entry_id INT UNSIGNED NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id), UNIQUE KEY uq_pro_weekly_cycle_day(cycle_id,streak_day),
 UNIQUE KEY uq_pro_weekly_user_date(user_id,checkin_date),
 KEY idx_pro_weekly_checkins_user(user_id,created_at),
 CONSTRAINT fk_pro_weekly_checkin_cycle FOREIGN KEY(cycle_id)
  REFERENCES pro_weekly_streak_cycles(id) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT fk_pro_weekly_checkin_user FOREIGN KEY(user_id)
  REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT fk_pro_weekly_checkin_ledger FOREIGN KEY(ledger_entry_id)
  REFERENCES reward_ledger(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
