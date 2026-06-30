<?php
// 비회원 댓글 CRUD. 글 비밀번호는 password_hash/password_verify로만 처리.
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function comments_list(PDO $pdo, int $limit = 30, int $offset = 0): array
{
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $stmt = $pdo->prepare(
        'SELECT id, nickname, content, weather_snapshot, created_at
         FROM comments
         ORDER BY id DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function comments_count(PDO $pdo): int
{
    return (int)$pdo->query('SELECT COUNT(*) AS c FROM comments')->fetch()['c'];
}

function comments_get(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, nickname, content, weather_snapshot, created_at
         FROM comments
         WHERE id = :id'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function comments_create(PDO $pdo, array $input): array
{
    $data = comments_validate($input, true);
    $stmt = $pdo->prepare(
        'INSERT INTO comments (nickname, content, password_hash, weather_snapshot)
         VALUES (:nickname, :content, :password_hash, :weather_snapshot)'
    );
    $stmt->execute([
        ':nickname' => $data['nickname'],
        ':content' => $data['content'],
        ':password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
        ':weather_snapshot' => $data['weather_snapshot'],
    ]);
    $comment = comments_get($pdo, (int)$pdo->lastInsertId());
    if ($comment === null) {
        throw new RuntimeException('created comment not found');
    }
    return $comment;
}

function comments_update(PDO $pdo, int $id, array $input): array
{
    $data = comments_validate($input, false);
    $hash = comments_password_hash_for_id($pdo, $id);
    if ($hash === null) {
        throw new CommentsNotFoundException('comment not found');
    }
    if (!password_verify($data['password'], $hash)) {
        throw new CommentsForbiddenException('invalid password');
    }

    $stmt = $pdo->prepare(
        'UPDATE comments
         SET nickname = :nickname, content = :content, weather_snapshot = :weather_snapshot
         WHERE id = :id'
    );
    $stmt->execute([
        ':id' => $id,
        ':nickname' => $data['nickname'],
        ':content' => $data['content'],
        ':weather_snapshot' => $data['weather_snapshot'],
    ]);
    $comment = comments_get($pdo, $id);
    if ($comment === null) {
        throw new CommentsNotFoundException('comment not found');
    }
    return $comment;
}

function comments_delete(PDO $pdo, int $id, string $password): void
{
    $password = trim($password);
    if ($password === '') {
        throw new InvalidArgumentException('password is required');
    }
    $hash = comments_password_hash_for_id($pdo, $id);
    if ($hash === null) {
        throw new CommentsNotFoundException('comment not found');
    }
    if (!password_verify($password, $hash)) {
        throw new CommentsForbiddenException('invalid password');
    }

    $stmt = $pdo->prepare('DELETE FROM comments WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

function comments_password_hash_for_id(PDO $pdo, int $id): ?string
{
    $stmt = $pdo->prepare('SELECT password_hash FROM comments WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ? (string)$row['password_hash'] : null;
}

function comments_validate(array $input, bool $requirePassword): array
{
    $nickname = trim((string)($input['nickname'] ?? ''));
    $content = trim((string)($input['content'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $weatherSnapshot = trim((string)($input['weather_snapshot'] ?? ''));

    if ($nickname === '' || comments_string_length($nickname) > 50) {
        throw new InvalidArgumentException('nickname must be 1-50 characters');
    }
    if ($content === '' || comments_string_length($content) > 1000) {
        throw new InvalidArgumentException('content must be 1-1000 characters');
    }
    if (($requirePassword || $password !== '') && comments_string_length($password) < 4) {
        throw new InvalidArgumentException('password must be at least 4 characters');
    }
    if ($weatherSnapshot !== '' && comments_string_length($weatherSnapshot) > 255) {
        throw new InvalidArgumentException('weather_snapshot must be 255 characters or less');
    }

    return [
        'nickname' => $nickname,
        'content' => $content,
        'password' => $password,
        'weather_snapshot' => $weatherSnapshot === '' ? null : $weatherSnapshot,
    ];
}

function comments_string_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function comments_positive_id(mixed $value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        throw new InvalidArgumentException('id must be a positive integer');
    }
    return (int)$id;
}

class CommentsNotFoundException extends RuntimeException
{
}

class CommentsForbiddenException extends RuntimeException
{
}
