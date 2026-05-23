<?php


$host     = 'localhost';
$dbname   = 'vibecheck';
$username = 'root';       
$password = '';           

try {
  $pdo = new PDO(
    "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
    $username,
    $password
  );
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Database connection failed']);
  exit;
}


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}


$method = $_SERVER['REQUEST_METHOD'];  
$action = $_GET['action'] ?? '';       

switch ($action) {

 

  case 'register':
    if ($method !== 'POST') break;

    $data = json_decode(file_get_contents('php://input'), true);

    $display_name = trim($data['display_name'] ?? '');
    $handle       = trim($data['handle']       ?? '');
    $initials     = trim($data['initials']     ?? '');
    $avatar_color = trim($data['avatar_color'] ?? '');

    if (!$display_name || !$handle) {
      echo json_encode(['error' => 'Name and handle are required']);
      exit;
    }

    
    $check = $pdo->prepare('SELECT id FROM users WHERE handle = ?');
    $check->execute([$handle]);
    if ($check->fetch()) {
      echo json_encode(['error' => 'Handle already taken']);
      exit;
    }

    $stmt = $pdo->prepare('
      INSERT INTO users (display_name, handle, initials, avatar_color)
      VALUES (?, ?, ?, ?)
    ');
    $stmt->execute([$display_name, $handle, $initials, $avatar_color]);
    $userId = $pdo->lastInsertId();


    $np = $pdo->prepare('INSERT INTO now_playing (user_id) VALUES (?)');
    $np->execute([$userId]);

    echo json_encode([
      'success' => true,
      'user_id' => $userId,
      'display_name' => $display_name,
      'handle'       => $handle,
      'initials'     => $initials,
      'avatar_color' => $avatar_color,
      'followers'    => 0,
      'following'    => 0,
      'post_count'   => 0,
      'current_vibe' => null,
    ]);
    break;

  

  case 'get_user':

    if ($method !== 'GET') break;

    $handle = $_GET['handle'] ?? '';
    if (!$handle) {
      echo json_encode(['error' => 'Handle is required']);
      exit;
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE handle = ?');
    $stmt->execute([$handle]);
    $user = $stmt->fetch();

    if (!$user) {
      echo json_encode(['error' => 'User not found']);
      exit;
    }

    
    $np = $pdo->prepare('SELECT * FROM now_playing WHERE user_id = ?');
    $np->execute([$user['id']]);
    $user['now_playing'] = $np->fetch() ?: null;

    echo json_encode($user);
    break;

  

  case 'update_vibe':
    
    if ($method !== 'POST') break;

    $data    = json_decode(file_get_contents('php://input'), true);
    $user_id = intval($data['user_id']      ?? 0);
    $vibe    = trim($data['current_vibe']   ?? '');
    $title   = trim($data['music_title']    ?? '');
    $artist  = trim($data['music_artist']   ?? '');

    if (!$user_id) {
      echo json_encode(['error' => 'User ID required']);
      exit;
    }

    $stmt = $pdo->prepare('
      UPDATE users SET current_vibe = ? WHERE id = ?
    ');
    $stmt->execute([$vibe ?: null, $user_id]);

    
    $np = $pdo->prepare('
      INSERT INTO now_playing (user_id, music_title, music_artist)
      VALUES (?, ?, ?)
      ON DUPLICATE KEY UPDATE
        music_title   = VALUES(music_title),
        music_artist  = VALUES(music_artist),
        updated_at    = CURRENT_TIMESTAMP
    ');
    $np->execute([$user_id, $title ?: null, $artist ?: null]);

    echo json_encode(['success' => true]);
    break;

  

  case 'get_posts':
    if ($method !== 'GET') break;

    $mood_filter = $_GET['mood'] ?? '';

    if ($mood_filter) {
      $stmt = $pdo->prepare('
        SELECT * FROM view_posts WHERE mood = ?
      ');
      $stmt->execute([$mood_filter]);
    } else {
      $stmt = $pdo->query('SELECT * FROM view_posts');
    }

    $posts = $stmt->fetchAll();
    echo json_encode($posts);
    break;


  case 'create_post':
    
    if ($method !== 'POST') break;

    $data    = json_decode(file_get_contents('php://input'), true);
    $user_id = intval($data['user_id']      ?? 0);
    $body    = trim($data['body']           ?? '');
    $mood    = trim($data['mood']           ?? '');
    $title   = trim($data['music_title']    ?? '');
    $artist  = trim($data['music_artist']   ?? '');

    if (!$user_id || !$body) {
      echo json_encode(['error' => 'User ID and post body are required']);
      exit;
    }

    
    $mood_id = null;
    if ($mood) {
      $moodStmt = $pdo->prepare('SELECT id FROM moods WHERE name = ?');
      $moodStmt->execute([$mood]);
      $moodRow = $moodStmt->fetch();
      $mood_id = $moodRow ? $moodRow['id'] : null;
    }

    // Insert the post
    $stmt = $pdo->prepare('
      INSERT INTO posts (user_id, body, mood_id, music_title, music_artist)
      VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([
      $user_id,
      $body,
      $mood_id,
      $title  ?: null,
      $artist ?: null,
    ]);
    $postId = $pdo->lastInsertId();

    // Increment user post count
    $pdo->prepare('UPDATE users SET post_count = post_count + 1 WHERE id = ?')
        ->execute([$user_id]);

    // Update current vibe and now playing if provided
    if ($mood) {
      $pdo->prepare('UPDATE users SET current_vibe = ? WHERE id = ?')
          ->execute([$mood, $user_id]);
    }
    if ($title) {
      $pdo->prepare('
        INSERT INTO now_playing (user_id, music_title, music_artist)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
          music_title  = VALUES(music_title),
          music_artist = VALUES(music_artist),
          updated_at   = CURRENT_TIMESTAMP
      ')->execute([$user_id, $title, $artist ?: null]);
    }

    
    if ($mood) {
      $tag = '#' . str_replace(' ', '', $mood);
      $pdo->prepare('
        INSERT INTO trending (tag, post_count)
        VALUES (?, 1)
        ON DUPLICATE KEY UPDATE post_count = post_count + 1
      ')->execute([$tag]);
    }

    
    $newPost = $pdo->prepare('SELECT * FROM view_posts WHERE id = ?');
    $newPost->execute([$postId]);

    echo json_encode([
      'success' => true,
      'post'    => $newPost->fetch(),
    ]);
    break;

  
  case 'toggle_like':

    if ($method !== 'POST') break;

    $data    = json_decode(file_get_contents('php://input'), true);
    $user_id = intval($data['user_id'] ?? 0);
    $post_id = intval($data['post_id'] ?? 0);

    if (!$user_id || !$post_id) {
      echo json_encode(['error' => 'User ID and post ID required']);
      exit;
    }

    $check = $pdo->prepare('
      SELECT id FROM likes WHERE user_id = ? AND post_id = ?
    ');
    $check->execute([$user_id, $post_id]);

    if ($check->fetch()) {
      $pdo->prepare('DELETE FROM likes WHERE user_id = ? AND post_id = ?')
          ->execute([$user_id, $post_id]);
      $pdo->prepare('UPDATE posts SET likes = likes - 1 WHERE id = ?')
          ->execute([$post_id]);
      $liked = false;
    } else {
      $pdo->prepare('INSERT INTO likes (user_id, post_id) VALUES (?, ?)')
          ->execute([$user_id, $post_id]);
      $pdo->prepare('UPDATE posts SET likes = likes + 1 WHERE id = ?')
          ->execute([$post_id]);

      // Notify the post owner
      $postOwner = $pdo->prepare('SELECT user_id FROM posts WHERE id = ?');
      $postOwner->execute([$post_id]);
      $owner = $postOwner->fetch();
      if ($owner && $owner['user_id'] !== $user_id) {
        $pdo->prepare('
          INSERT INTO notifications (user_id, from_user_id, type, post_id)
          VALUES (?, ?, "like", ?)
        ')->execute([$owner['user_id'], $user_id, $post_id]);
      }

      $liked = true;
    }

    // Get updated like count
    $count = $pdo->prepare('SELECT likes FROM posts WHERE id = ?');
    $count->execute([$post_id]);
    $row = $count->fetch();

    echo json_encode([
      'success' => true,
      'liked'   => $liked,
      'likes'   => $row['likes'],
    ]);
    break;

  

  case 'toggle_relate':
    // Relate or un-relate a post
    if ($method !== 'POST') break;

    $data    = json_decode(file_get_contents('php://input'), true);
    $user_id = intval($data['user_id'] ?? 0);
    $post_id = intval($data['post_id'] ?? 0);

    if (!$user_id || !$post_id) {
      echo json_encode(['error' => 'User ID and post ID required']);
      exit;
    }

    $check = $pdo->prepare('
      SELECT id FROM relates WHERE user_id = ? AND post_id = ?
    ');
    $check->execute([$user_id, $post_id]);

    if ($check->fetch()) {
      $pdo->prepare('DELETE FROM relates WHERE user_id = ? AND post_id = ?')
          ->execute([$user_id, $post_id]);
      $pdo->prepare('UPDATE posts SET relates = relates - 1 WHERE id = ?')
          ->execute([$post_id]);
      $related = false;
    } else {
      $pdo->prepare('INSERT INTO relates (user_id, post_id) VALUES (?, ?)')
          ->execute([$user_id, $post_id]);
      $pdo->prepare('UPDATE posts SET relates = relates + 1 WHERE id = ?')
          ->execute([$post_id]);
      $related = true;
    }

    $count = $pdo->prepare('SELECT relates FROM posts WHERE id = ?');
    $count->execute([$post_id]);
    $row = $count->fetch();

    echo json_encode([
      'success' => true,
      'related' => $related,
      'relates' => $row['relates'],
    ]);
    break;

 

  case 'get_comments':
    if ($method !== 'GET') break;

    $post_id = intval($_GET['post_id'] ?? 0);
    if (!$post_id) {
      echo json_encode(['error' => 'Post ID required']);
      exit;
    }

    $stmt = $pdo->prepare('
      SELECT
        c.id,
        c.body,
        c.created_at,
        u.display_name,
        u.handle,
        u.initials,
        u.avatar_color
      FROM comments c
      JOIN users u ON c.user_id = u.id
      WHERE c.post_id = ?
      ORDER BY c.created_at ASC
    ');
    $stmt->execute([$post_id]);
    echo json_encode($stmt->fetchAll());
    break;

  

  case 'add_comment':
    if ($method !== 'POST') break;

    $data    = json_decode(file_get_contents('php://input'), true);
    $user_id = intval($data['user_id'] ?? 0);
    $post_id = intval($data['post_id'] ?? 0);
    $body    = trim($data['body']      ?? '');

    if (!$user_id || !$post_id || !$body) {
      echo json_encode(['error' => 'User ID, post ID and comment body required']);
      exit;
    }

    $stmt = $pdo->prepare('
      INSERT INTO comments (post_id, user_id, body) VALUES (?, ?, ?)
    ');
    $stmt->execute([$post_id, $user_id, $body]);

    // Increment comment count on post
    $pdo->prepare('UPDATE posts SET comments = comments + 1 WHERE id = ?')
        ->execute([$post_id]);

    $postOwner = $pdo->prepare('SELECT user_id FROM posts WHERE id = ?');
    $postOwner->execute([$post_id]);
    $owner = $postOwner->fetch();
    if ($owner && $owner['user_id'] !== $user_id) {
      $pdo->prepare('
        INSERT INTO notifications (user_id, from_user_id, type, post_id)
        VALUES (?, ?, "comment", ?)
      ')->execute([$owner['user_id'], $user_id, $post_id]);
    }

    echo json_encode(['success' => true]);
    break;

  

  case 'toggle_follow':
    if ($method !== 'POST') break;

    $data         = json_decode(file_get_contents('php://input'), true);
    $follower_id  = intval($data['follower_id']  ?? 0);
    $following_id = intval($data['following_id'] ?? 0);

    if (!$follower_id || !$following_id || $follower_id === $following_id) {
      echo json_encode(['error' => 'Invalid user IDs']);
      exit;
    }

    $check = $pdo->prepare('
      SELECT id FROM followers WHERE follower_id = ? AND following_id = ?
    ');
    $check->execute([$follower_id, $following_id]);

    if ($check->fetch()) {
    
      $pdo->prepare('
        DELETE FROM followers WHERE follower_id = ? AND following_id = ?
      ')->execute([$follower_id, $following_id]);
      $pdo->prepare('UPDATE users SET followers = followers - 1 WHERE id = ?')
          ->execute([$following_id]);
      $pdo->prepare('UPDATE users SET following = following - 1 WHERE id = ?')
          ->execute([$follower_id]);
      $following = false;
    } else {
    
      $pdo->prepare('
        INSERT INTO followers (follower_id, following_id) VALUES (?, ?)
      ')->execute([$follower_id, $following_id]);
      $pdo->prepare('UPDATE users SET followers = followers + 1 WHERE id = ?')
          ->execute([$following_id]);
      $pdo->prepare('UPDATE users SET following = following + 1 WHERE id = ?')
          ->execute([$follower_id]);


      $pdo->prepare('
        INSERT INTO notifications (user_id, from_user_id, type)
        VALUES (?, ?, "follow")
      ')->execute([$following_id, $follower_id]);

      $following = true;
    }

    echo json_encode(['success' => true, 'following' => $following]);
    break;



  case 'get_moods':
    
    if ($method !== 'GET') break;

    $stmt = $pdo->query('SELECT id, name FROM moods ORDER BY name ASC');
    echo json_encode($stmt->fetchAll());
    break;

  

  case 'get_trending':
    if ($method !== 'GET') break;

    $stmt = $pdo->query('SELECT * FROM view_trending');
    echo json_encode($stmt->fetchAll());
    break;

  

  case 'get_notifications':
    if ($method !== 'GET') break;

    $user_id = intval($_GET['user_id'] ?? 0);
    if (!$user_id) {
      echo json_encode(['error' => 'User ID required']);
      exit;
    }

    $stmt = $pdo->prepare('
      SELECT
        n.id,
        n.type,
        n.is_read,
        n.created_at,
        u.display_name AS from_name,
        u.handle       AS from_handle,
        n.post_id
      FROM notifications n
      LEFT JOIN users u ON n.from_user_id = u.id
      WHERE n.user_id = ?
      ORDER BY n.created_at DESC
      LIMIT 30
    ');
    $stmt->execute([$user_id]);
    echo json_encode($stmt->fetchAll());
    break;


  case 'mark_notifications_read':
    if ($method !== 'POST') break;

    $data    = json_decode(file_get_contents('php://input'), true);
    $user_id = intval($data['user_id'] ?? 0);

    if (!$user_id) {
      echo json_encode(['error' => 'User ID required']);
      exit;
    }

    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')
        ->execute([$user_id]);

    echo json_encode(['success' => true]);
    break;

  

  case 'get_unread_count':
    if ($method !== 'GET') break;

    $user_id = intval($_GET['user_id'] ?? 0);
    if (!$user_id) {
      echo json_encode(['count' => 0]);
      exit;
    }

    $stmt = $pdo->prepare('
      SELECT COUNT(*) AS count
      FROM notifications
      WHERE user_id = ? AND is_read = 0
    ');
    $stmt->execute([$user_id]);
    echo json_encode($stmt->fetch());
    break;


  default:
    http_response_code(404);
    echo json_encode(['error' => 'Action not found']);
    break;
}
?>