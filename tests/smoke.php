<?php

declare(strict_types=1);

const BIN = __DIR__ . '/../bin/opencode-search';

$base = sys_get_temp_dir() . '/opencode-search-smoke-' . getmypid();
exec('rm -rf ' . escapeshellarg($base));
$home = $base;
$dir  = $home . '/.local/share/opencode';
mkdir($dir, 0777, true);

$db = new PDO('sqlite:' . $dir . '/opencode.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec(
    'CREATE TABLE session (
        id TEXT PRIMARY KEY,
        project_id TEXT NOT NULL,
        workspace_id TEXT,
        parent_id TEXT,
        slug TEXT NOT NULL,
        directory TEXT NOT NULL,
        path TEXT,
        title TEXT NOT NULL,
        version TEXT NOT NULL,
        share_url TEXT,
        summary_additions INTEGER,
        summary_deletions INTEGER,
        summary_files INTEGER,
        summary_diffs TEXT,
        metadata TEXT,
        cost REAL DEFAULT 0 NOT NULL,
        tokens_input INTEGER DEFAULT 0 NOT NULL,
        tokens_output INTEGER DEFAULT 0 NOT NULL,
        tokens_reasoning INTEGER DEFAULT 0 NOT NULL,
        tokens_cache_read INTEGER DEFAULT 0 NOT NULL,
        tokens_cache_write INTEGER DEFAULT 0 NOT NULL,
        revert TEXT,
        permission TEXT,
        agent TEXT,
        model TEXT,
        time_created INTEGER NOT NULL,
        time_updated INTEGER NOT NULL,
        time_compacting INTEGER,
        time_archived INTEGER
     );
     CREATE TABLE message (
        id TEXT PRIMARY KEY,
        session_id TEXT NOT NULL,
        time_created INTEGER NOT NULL,
        time_updated INTEGER NOT NULL,
        data TEXT NOT NULL
     );
     CREATE TABLE part (
        id TEXT PRIMARY KEY,
        message_id TEXT NOT NULL,
        session_id TEXT NOT NULL,
        time_created INTEGER NOT NULL,
        time_updated INTEGER NOT NULL,
        data TEXT NOT NULL
     );'
);

$now = time() * 1000;

$ins = $db->prepare('INSERT INTO session (id, project_id, slug, directory, title, version, time_created, time_updated) VALUES (?,?,?,?,?,?,?,?)');
$ins->execute(['ses_fixture0001aaa', 'p1', 'fixture-1', '/tmp/proj-a', 'Fixture session one', '1', $now, $now]);
$ins->execute(['ses_fixture0002bbb', 'p1', 'fixture-2', '/tmp/proj-b', 'Fixture session two', '1', $now, $now]);

$ins = $db->prepare('INSERT INTO message (id, session_id, time_created, time_updated, data) VALUES (?,?,?,?,?)');
$ins->execute(['m1', 'ses_fixture0001aaa', $now, $now, '{"role":"user"}']);
$ins->execute(['m2', 'ses_fixture0001aaa', $now, $now, '{"role":"assistant"}']);
$ins->execute(['m3', 'ses_fixture0002bbb', $now, $now, '{"role":"user"}']);

$ins = $db->prepare('INSERT INTO part (id, message_id, session_id, time_created, time_updated, data) VALUES (?,?,?,?,?,?)');
$ins->execute(['p1', 'm1', 'ses_fixture0001aaa', $now, $now, json_encode(['type' => 'text', 'text' => 'hello uniquefixturekw world'])]);
$ins->execute(['p2', 'm2', 'ses_fixture0001aaa', $now, $now, json_encode(['type' => 'tool', 'tool' => 'bash', 'state' => ['status' => 'completed', 'output' => 'tool output uniquefixturekw toolkw_marker here']])]);
$ins->execute(['p3', 'm3', 'ses_fixture0002bbb', $now, $now, json_encode(['type' => 'text', 'text' => 'second session content'])]);
$ins->execute(['p4', 'm2', 'ses_fixture0001aaa', $now, $now, json_encode(['type' => 'text', 'text' => "under_score literal\nunderXscore not literal"])]);
$db = null;

$failures = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    if ($ok) {
        echo "  ok  $name\n";

        return;
    }
    $failures++;
    echo " FAIL  $name" . ($detail !== '' ? " — $detail" : '') . "\n";
}

function run(string $args): array
{
    global $home;
    putenv('HOME=' . $home);
    putenv('PATH=/usr/bin:/bin');

    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(BIN) . ' ' . $args . ' 2>&1';
    exec($cmd, $out, $code);

    return [$code, implode("\n", $out)];
}

[$code, $out] = run('uniquefixturekw');
check('list: exit 0 on match', $code === 0, "exit=$code out=$out");
check('list: contains matching session', str_contains($out, 'ses_fixture0001aaa'));
check('list: excludes non-matching session', !str_contains($out, 'ses_fixture0002bbb'));

[$code, $out] = run('uniquefixturekw --text-only');
check('list --text-only: exit 0', $code === 0, "exit=$code out=$out");

[$code, $out] = run('toolkw_marker');
check('list: tool output matched by default', $code === 0 && str_contains($out, 'ses_fixture0001aaa'), "exit=$code");

[$code, $out] = run('toolkw_marker --text-only');
check('list --text-only: tool output ignored', $code === 1, "exit=$code out=$out");

[$code, $out] = run('uniquefixturekw --project proj-a');
check('list --project match: exit 0', $code === 0, "exit=$code out=$out");

[$code, $out] = run('uniquefixturekw --project proj-b');
check('list --project no match: exit 1', $code === 1, "exit=$code out=$out");

[$code, $out] = run('zzz_no_match_xyz');
check('list: no match exit 1', $code === 1, "exit=$code out=$out");

[$code, $out] = run('uniquefixturekw --json');
$data = json_decode($out, true);
check('list --json: exit 0', $code === 0, "exit=$code out=$out");
check('list --json: valid payload', is_array($data) && $data['count'] === 1 && $data['sessions'][0]['id'] === 'ses_fixture0001aaa');

[$code, $out] = run('uniquefixturekw --quiet');
check('list --quiet: ID only', $code === 0 && trim($out) === 'ses_fixture0001aaa', "exit=$code out=" . var_export($out, true));

[$code, $out] = run('uniquefixturekw --resume');
check('list --resume: shows session command', $code === 0 && str_contains($out, 'opencode --session ses_fixture0001aaa'));

[$code, $out] = run('uniquefixturekw --since -1d');
check('list --since -1d: exit 0', $code === 0, "exit=$code out=$out");

[$code, $out] = run('uniquefixturekw --since banana');
check('list --since invalid: exit 2', $code === 2, "exit=$code out=$out");

[$code, $out] = run('uniquefixturekw --show ses_fixture0001');
check('show by prefix: exit 0', $code === 0, "exit=$code out=$out");
check('show: session header', str_contains($out, 'Fixture session one'));
check('show: user role label', str_contains($out, 'user'));
check('show: tool role label', str_contains($out, 'tool:bash'));

[$code, $out] = run('under_score --show ses_fixture0001aaa --context 0');
check('show: escaped underscore matches literal', $code === 0 && str_contains($out, 'under_score literal'), "exit=$code out=$out");
check('show: escaped underscore does not wildcard', !str_contains($out, 'underXscore'), $out);

[$code, $out] = run('-h');
check('help: exit 0', $code === 0, "exit=$code");
check('help: shows usage', str_contains($out, 'Usage:'));

[$code, $out] = run('uniquefixturekw --json --show ses_fixture0001');
check('conflicting flags: exit 2', $code === 2, "exit=$code out=$out");

exec('rm -rf ' . escapeshellarg($base));

if ($failures > 0) {
    echo "\n$failures test(s) failed.\n";
    exit(1);
}

echo "\nAll tests passed.\n";
