<?php

namespace Tests\MediaWiki\Extensions\FlarumAuth;

use Closure;
use MediaWiki\Extensions\FlarumAuth\FlarumUserLookup;
use MediaWiki\Extensions\FlarumAuth\FlarumUserPurger;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

class FlarumUserPurgerTest extends TestCase
{
    private FlarumUserLookup&Stub $lookup;
    private IConnectionProvider&Stub $connectionProvider;
    /** @var list<int> */
    private array $mergedUserIds = [];

    /** @var list<string> */
    private array $output = [];
    /** @var list<string> */
    private array $errors = [];

    protected function setUp(): void
    {
        $this->lookup = $this->createStub(FlarumUserLookup::class);
        $this->connectionProvider = $this->createStub(IConnectionProvider::class);
        $this->mergedUserIds = [];
        $this->output = [];
        $this->errors = [];
    }

    /**
     * @param list<object{user_id: int, user_name: string}> $rows
     */
    private function setupDatabase(array $rows): void
    {
        $iterator = new \ArrayIterator($rows);
        $resultWrapper = $this->createStub(\Wikimedia\Rdbms\IResultWrapper::class);
        $resultWrapper->method('current')->willReturnCallback(fn () => $iterator->current());
        $resultWrapper->method('key')->willReturnCallback(fn () => $iterator->key());
        $resultWrapper->method('next')->willReturnCallback(fn () => $iterator->next());
        $resultWrapper->method('valid')->willReturnCallback(fn () => $iterator->valid());
        $resultWrapper->method('rewind')->willReturnCallback(fn () => $iterator->rewind());

        $expr = $this->createStub(\Wikimedia\Rdbms\Expression::class);

        $queryBuilder = $this->createStub(SelectQueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('caller')->willReturnSelf();
        $queryBuilder->method('fetchResultSet')->willReturn($resultWrapper);

        $dbr = $this->createStub(IReadableDatabase::class);
        $dbr->method('newSelectQueryBuilder')->willReturn($queryBuilder);
        $dbr->method('expr')->willReturn($expr);

        $this->connectionProvider->method('getReplicaDatabase')->willReturn($dbr);
    }

    /**
     * @return Closure(int): void
     */
    private function createMergeAndDelete(?\Exception $exception = null): Closure
    {
        return function (int $userId) use ($exception): void {
            $this->mergedUserIds[] = $userId;
            if ($exception !== null) {
                throw $exception;
            }
        };
    }

    private function createPurger(?\Exception $mergeException = null): FlarumUserPurger
    {
        return new FlarumUserPurger(
            $this->lookup,
            $this->connectionProvider,
            $this->createMergeAndDelete($mergeException),
        );
    }

    private function outputCallback(): Closure
    {
        return function (string $msg): void {
            $this->output[] = $msg;
        };
    }

    private function errorCallback(): Closure
    {
        return function (string $msg): void {
            $this->errors[] = $msg;
        };
    }

    public function testAllUsersExist(): void
    {
        $this->setupDatabase([
            (object)['user_id' => 1, 'user_name' => 'Alice'],
            (object)['user_id' => 2, 'user_name' => 'Bob'],
        ]);

        $this->lookup->method('exists')->willReturn(true);

        $purger = $this->createPurger();
        $result = $purger->purge($this->outputCallback(), $this->errorCallback(), false);

        $this->assertSame(['purged' => 0, 'skipped' => 2, 'errors' => 0], $result);
        $this->assertEmpty($this->mergedUserIds);
    }

    public function testUserNotFound(): void
    {
        $this->setupDatabase([
            (object)['user_id' => 1, 'user_name' => 'Gone'],
        ]);

        $this->lookup->method('exists')->willReturn(false);

        $purger = $this->createPurger();
        $result = $purger->purge($this->outputCallback(), $this->errorCallback(), false);

        $this->assertSame(['purged' => 1, 'skipped' => 0, 'errors' => 0], $result);
        $this->assertSame([1], $this->mergedUserIds);
        $this->assertStringContainsString('Purging: Gone', $this->output[0]);
    }

    public function testLookupReturnsNull(): void
    {
        $this->setupDatabase([
            (object)['user_id' => 1, 'user_name' => 'Broken'],
        ]);

        $this->lookup->method('exists')->willReturn(null);

        $purger = $this->createPurger();
        $result = $purger->purge($this->outputCallback(), $this->errorCallback(), false);

        $this->assertSame(['purged' => 0, 'skipped' => 0, 'errors' => 1], $result);
        $this->assertEmpty($this->mergedUserIds);
        $this->assertStringContainsString('Unexpected API response for Broken', $this->errors[0]);
    }

    public function testDryRun(): void
    {
        $this->setupDatabase([
            (object)['user_id' => 1, 'user_name' => 'Gone'],
        ]);

        $this->lookup->method('exists')->willReturn(false);

        $purger = $this->createPurger();
        $result = $purger->purge($this->outputCallback(), $this->errorCallback(), true);

        $this->assertSame(['purged' => 1, 'skipped' => 0, 'errors' => 0], $result);
        $this->assertEmpty($this->mergedUserIds);
        $this->assertStringContainsString('Would purge: Gone', $this->output[0]);
    }

    public function testMergeThrowsException(): void
    {
        $this->setupDatabase([
            (object)['user_id' => 1, 'user_name' => 'Failing'],
            (object)['user_id' => 2, 'user_name' => 'AlsoGone'],
        ]);

        $this->lookup->method('exists')->willReturn(false);

        $purger = $this->createPurger(new \RuntimeException('merge failed'));
        $result = $purger->purge($this->outputCallback(), $this->errorCallback(), false);

        $this->assertSame(['purged' => 0, 'skipped' => 0, 'errors' => 2], $result);
        $this->assertSame([1, 2], $this->mergedUserIds);
        $this->assertStringContainsString('Failed to purge Failing', $this->errors[0]);
        $this->assertStringContainsString('Failed to purge AlsoGone', $this->errors[1]);
    }
}
