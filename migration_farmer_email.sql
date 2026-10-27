USE herdsignal_zw;

-- Run this once on an existing HerdSignal ZW database.
ALTER TABLE users ADD COLUMN community VARCHAR(120) NULL AFTER email;
ALTER TABLE reminders ADD COLUMN email_sent_at DATETIME NULL AFTER status;
ALTER TABLE reminders ADD COLUMN email_last_error VARCHAR(255) NULL AFTER email_sent_at;

-- Existing users can add an email in phpMyAdmin if they want reminders.
