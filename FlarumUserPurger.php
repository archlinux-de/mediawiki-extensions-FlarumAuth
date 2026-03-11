<?php

namespace MediaWiki\Extensions\FlarumAuth;

use Closure;
use Wikimedia\Rdbms\IConnectionProvider;

class FlarumUserPurger
{
    /** @var Closure(int): void */
    private Closure $mergeAndDeleteUser;

    /**
     * @param Closure(int): void $mergeAndDeleteUser Callable that merges and deletes a user by ID
     */
    public function __construct(
        private readonly FlarumUserLookup $lookup,
        private readonly IConnectionProvider $connectionProvider,
        Closure $mergeAndDeleteUser,
    ) {
        $this->mergeAndDeleteUser = $mergeAndDeleteUser;
    }

    /**
     * @param callable(string): void $output
     * @param callable(string): void $error
     * @return array{purged: int, skipped: int, errors: int}
     */
    public function purge(callable $output, callable $error, bool $dryRun): array
    {
        $dbr = $this->connectionProvider->getReplicaDatabase();

        $res = $dbr->newSelectQueryBuilder()
            ->select(['user_id', 'user_name'])
            ->from('user')
            ->where($dbr->expr('user_id', '>', 0)) // @phpstan-ignore argument.type
            ->caller(__METHOD__)
            ->fetchResultSet();

        $purged = 0;
        $skipped = 0;
        $errors = 0;

        /** @var object{user_id: int, user_name: string} $row */
        foreach ($res as $row) {
            $wikiUsername = $row->user_name;

            $exists = $this->lookup->exists($wikiUsername);

            if ($exists === null) {
                $error("Unexpected API response for $wikiUsername, skipping\n");
                $errors++;
                continue;
            }

            if ($exists) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $output("Would purge: $wikiUsername (ID: {$row->user_id})\n");
                $purged++;
                continue;
            }

            $output("Purging: $wikiUsername (ID: {$row->user_id})\n");

            try {
                ($this->mergeAndDeleteUser)((int)$row->user_id);
                $purged++;
            } catch (\Exception $e) {
                $error("Failed to purge $wikiUsername: " . $e->getMessage() . "\n");
                $errors++;
            }
        }

        return ['purged' => $purged, 'skipped' => $skipped, 'errors' => $errors];
    }
}
