-- Migration: Add learning_title and learning_url columns to mini_tasks
-- Date: 2026-05-21
-- Purpose: Allow admin to override hardcoded learning_title/learning_url per mission task

ALTER TABLE mini_tasks
  ADD COLUMN IF NOT EXISTS learning_title VARCHAR(255) NOT NULL DEFAULT '' AFTER min_quiz_score,
  ADD COLUMN IF NOT EXISTS learning_url VARCHAR(500) NOT NULL DEFAULT '' AFTER learning_title;
