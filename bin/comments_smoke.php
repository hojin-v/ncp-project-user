<?php
// 댓글 CRUD 라운드트립 테스트. 테스트 행은 마지막에 삭제.
declare(strict_types=1);

require __DIR__ . '/../src/comments.php';

$pdo = db();
$password = 'test1234';

try {
    $created = comments_create($pdo, [
        'nickname' => '테스터',
        'content' => '댓글 CRUD 테스트',
        'password' => $password,
        'weather_snapshot' => '서울 25.0도',
    ]);
    printf("[CREATE] id=%d nickname=%s\n", $created['id'], $created['nickname']);

    $listed = comments_list($pdo, 5, 0);
    $found = array_filter($listed, fn (array $row): bool => (int)$row['id'] === (int)$created['id']);
    printf("[LIST] found=%s total_sample=%d\n", $found !== [] ? 'yes' : 'no', count($listed));

    $updated = comments_update($pdo, (int)$created['id'], [
        'nickname' => '테스터2',
        'content' => '수정된 댓글 CRUD 테스트',
        'password' => $password,
        'weather_snapshot' => '부산 20.7도',
    ]);
    printf("[UPDATE] id=%d nickname=%s\n", $updated['id'], $updated['nickname']);

    try {
        comments_delete($pdo, (int)$created['id'], 'wrong-password');
        throw new RuntimeException('wrong password delete unexpectedly succeeded');
    } catch (CommentsForbiddenException) {
        echo "[DELETE wrong password] forbidden\n";
    }

    comments_delete($pdo, (int)$created['id'], $password);
    printf("[DELETE] id=%d\n", $created['id']);

    $deleted = comments_get($pdo, (int)$created['id']);
    printf("[VERIFY] deleted=%s\n", $deleted === null ? 'yes' : 'no');
    echo "OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL " . $e->getMessage() . PHP_EOL);
    exit(1);
}
