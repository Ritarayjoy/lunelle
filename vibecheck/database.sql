
CREATE DATABASE IF NOT EXISTS vibecheck;
USE vibecheck;


CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  display_name  VARCHAR(100)        NOT NULL,
  handle        VARCHAR(100)        NOT NULL UNIQUE,
  initials      VARCHAR(5)          NOT NULL,
  avatar_color  VARCHAR(255)        DEFAULT NULL,
  bio           TEXT                DEFAULT NULL,
  followers     INT                 DEFAULT 0,
  following     INT                 DEFAULT 0,
  post_count    INT                 DEFAULT 0,
  current_vibe  VARCHAR(100)        DEFAULT NULL,
  created_at    DATETIME            DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME            DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS moods (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100)        NOT NULL UNIQUE,
  created_at    DATETIME            DEFAULT CURRENT_TIMESTAMP
);


INSERT IGNORE INTO moods (name) VALUES
  ('Happy'),
  ('Hyped'),
  ('Sad'),
  ('Chill'),
  ('Frustrated'),
  ('Loved Up'),
  ('Sleepy'),
  ('Excited'),
  ('Angry'),
  ('Anxious'),
  ('Grateful'),
  ('Bored');

CREATE TABLE IF NOT EXISTS posts (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT                 NOT NULL,
  body          TEXT                NOT NULL,
  mood_id       INT                 DEFAULT NULL,
  music_title   VARCHAR(255)        DEFAULT NULL,
  music_artist  VARCHAR(255)        DEFAULT NULL,
  likes         INT                 DEFAULT 0,
  comments      INT                 DEFAULT 0,
  relates       INT                 DEFAULT 0,
  created_at    DATETIME            DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME            DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (mood_id)  REFERENCES moods(id) ON DELETE SET NULL
);


CREATE TABLE IF NOT EXISTS likes (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT                 NOT NULL,
  post_id       INT                 NOT NULL,
  created_at    DATETIME            DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_like (user_id, post_id),
  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (post_id)  REFERENCES posts(id) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS relates (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT                 NOT NULL,
  post_id       INT                 NOT NULL,
  created_at    DATETIME            DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_relate (user_id, post_id),
  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (post_id)  REFERENCES posts(id) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS comments (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  post_id       INT                 NOT NULL,
  user_id       INT                 NOT NULL,
  body          TEXT                NOT NULL,
  created_at    DATETIME            DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME            DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id)  REFERENCES posts(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS followers (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  follower_id   INT                 NOT NULL,
  following_id  INT                 NOT NULL,
  created_at    DATETIME            DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_follow (follower_id, following_id),
  FOREIGN KEY (follower_id)   REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (following_id)  REFERENCES users(id) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS now_playing (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT                 NOT NULL UNIQUE,
  music_title   VARCHAR(255)        DEFAULT NULL,
  music_artist  VARCHAR(255)        DEFAULT NULL,
  updated_at    DATETIME            DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS trending (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  tag           VARCHAR(100)        NOT NULL UNIQUE,
  post_count    INT                 DEFAULT 1,
  updated_at    DATETIME            DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS notifications (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT                 NOT NULL,
  from_user_id  INT                 DEFAULT NULL,
  type          ENUM(
                  'like',
                  'comment',
                  'relate',
                  'follow',
                  'mention'
                )                   NOT NULL,
  post_id       INT                 DEFAULT NULL,
  is_read       TINYINT(1)          DEFAULT 0,
  created_at    DATETIME            DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)       REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (from_user_id)  REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (post_id)       REFERENCES posts(id) ON DELETE CASCADE
);


CREATE OR REPLACE VIEW view_posts AS
  SELECT
    p.id,
    p.body,
    p.likes,
    p.comments,
    p.relates,
    p.music_title,
    p.music_artist,
    p.created_at,
    u.display_name,
    u.handle,
    u.initials,
    u.avatar_color,
    m.name AS mood
  FROM posts p
  JOIN users u ON p.user_id = u.id
  LEFT JOIN moods m ON p.mood_id = m.id
  ORDER BY p.created_at DESC;


CREATE OR REPLACE VIEW view_trending AS
  SELECT tag, post_count
  FROM trending
  ORDER BY post_count DESC
  LIMIT 10;


CREATE OR REPLACE VIEW view_unread_notifications AS
  SELECT
    n.user_id,
    COUNT(*) AS unread_count
  FROM notifications n
  WHERE n.is_read = 0
  GROUP BY n.user_id;

