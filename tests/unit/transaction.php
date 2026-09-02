<?php

declare(strict_types=1);

use Minn\Db;

/**
 * The unit of work: everything a closure writes lands or none of it does.
 * These cases prove the control flow against a stand-in connection, since a
 * real rollback belongs to the write suites; what matters here is that the
 * door begins, commits, rolls back, and rethrows in the right order, and
 * that a nested call joins the transaction already open instead of starting
 * a second one the storage engine cannot give it.
 */
final class TransactionProbe extends mysqli
{
    /** @var list<string> */
    public array $calls = [];

    public function begin_transaction(int $flags = 0, ?string $name = null): bool
    {
        $this->calls[] = 'begin';
        return true;
    }

    public function commit(int $flags = 0, ?string $name = null): bool
    {
        $this->calls[] = 'commit';
        return true;
    }

    public function rollback(int $flags = 0, ?string $name = null): bool
    {
        $this->calls[] = 'rollback';
        return true;
    }
}

$door = static function (): array {
    $probe = new TransactionProbe();
    return [new Db($probe, 'wp_'), $probe];
};

return [
    'work that finishes commits once, and its value comes back' => static function () use ($door): bool|string {
        [$db, $probe] = $door();
        $out = $db->transaction(static fn (): string => 'the id');
        return $out === 'the id' && $probe->calls === ['begin', 'commit'] ? true : json_encode([$out, $probe->calls]);
    },
    'work that throws rolls back and the failure carries on' => static function () use ($door): bool|string {
        [$db, $probe] = $door();
        try {
            $db->transaction(static function (): never {
                throw new RuntimeException('half way');
            });
            return 'the failure was swallowed';
        } catch (RuntimeException $e) {
            return $e->getMessage() === 'half way' && $probe->calls === ['begin', 'rollback'] ? true : json_encode([$e->getMessage(), $probe->calls]);
        }
    },
    'a nested unit joins the one already open: one begin, one commit' => static function () use ($door): bool|string {
        [$db, $probe] = $door();
        $db->transaction(static function () use ($db): void {
            $db->transaction(static fn (): bool => true);
        });
        return $probe->calls === ['begin', 'commit'] ? true : json_encode($probe->calls);
    },
    'a failure leaves the door ready for the next unit of work' => static function () use ($door): bool|string {
        [$db, $probe] = $door();
        try {
            $db->transaction(static function (): never {
                throw new RuntimeException('first');
            });
        } catch (RuntimeException) {
        }
        $db->transaction(static fn (): bool => true);
        return $probe->calls === ['begin', 'rollback', 'begin', 'commit'] ? true : json_encode($probe->calls);
    },
];
