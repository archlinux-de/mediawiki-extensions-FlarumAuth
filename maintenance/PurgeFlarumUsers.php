<?php

use MediaWiki\Extension\UserMerge\MergeUser;
use MediaWiki\Extension\UserMerge\UserMergeLogger;
use MediaWiki\Extensions\FlarumAuth\FlarumUserLookup;
use MediaWiki\Extensions\FlarumAuth\FlarumUserPurger;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\User;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class PurgeFlarumUsers extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Purge MediaWiki users that no longer exist in Flarum' );
		$this->addOption( 'dry-run', 'List users that would be purged without making changes' );
		$this->addOption( 'canary', 'Username that must exist in Flarum (sanity check before purging)', false, true );
		$this->requireExtension( 'FlarumAuth' );
		$this->requireExtension( 'UserMerge' );
	}

	public function execute(): void {
		$services = MediaWikiServices::getInstance();

		$config = $services->getConfigFactory()->makeConfig( 'FlarumAuth' );
		$flarumUrl = $config->get( 'FlarumUrl' );
		if ( !is_string( $flarumUrl ) || $flarumUrl === '' ) {
			$this->fatalError( '$wgFlarumUrl is not configured' );
		}

		$userFactory = $services->getUserFactory();

		$anonymousUser = $userFactory->newFromName( 'Anonymous' );
		if ( !$anonymousUser || $anonymousUser->getId() === 0 ) {
			$this->fatalError( 'Anonymous user does not exist, cannot merge users' );
		}

		$client = $services->getHttpRequestFactory()
			->createGuzzleClient( [ 'base_uri' => $flarumUrl ] );
		$blockStore = $services->getDatabaseBlockStore();

		$performer = User::newSystemUser( 'Maintenance script', [ 'steal' => true ] );

		$lookup = new FlarumUserLookup( $client );

		$canary = $this->getOption( 'canary' );
		if ( $canary !== null ) {
			$canaryResult = $lookup->exists( $canary );
			if ( $canaryResult !== true ) {
				$this->fatalError(
					"Canary check failed: user '$canary' was not found in Flarum. "
					. 'Aborting to prevent accidental mass-purge. '
					. 'Verify that Flarum is reachable and the API is working correctly.'
				);
			}
			$this->output( "Canary check passed: '$canary' exists in Flarum\n" );
		}

		$purger = new FlarumUserPurger(
			$lookup,
			$services->getConnectionProvider(),
			function ( int $userId ) use ( $userFactory, $performer, $anonymousUser, $blockStore ): void {
				$oldUser = $userFactory->newFromId( $userId );
				$oldUser->load();
				$um = new MergeUser(
					$oldUser,
					$anonymousUser,
					new UserMergeLogger(),
					$blockStore,
					MergeUser::USE_MULTI_COMMIT
				);
				$um->merge( $performer, __METHOD__ );
				$um->delete( $performer, fn ( ...$args ) => wfMessage( ...$args ) );
			},
		);

		$dryRun = $this->hasOption( 'dry-run' );
		$result = $purger->purge(
			fn ( string $msg ) => $this->output( $msg ),
			fn ( string $msg ) => $this->error( $msg ),
			$dryRun,
		);

		$prefix = $dryRun ? '[DRY RUN] ' : '';
		$this->output(
			"\n{$prefix}Done. Purged: {$result['purged']}, Skipped: {$result['skipped']}, Errors: {$result['errors']}\n"
		);
	}
}

$maintClass = PurgeFlarumUsers::class;
require_once RUN_MAINTENANCE_IF_MAIN;
