USE koinrex;

ALTER TABLE blog_ads
    MODIFY placement ENUM('blog_leaderboard','blog_infeed','blog_sidebar','boosthub_spotlight','learnhub_spotlight','dashboard_rexhub_spotlight') NOT NULL;