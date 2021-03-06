<?hh // strict
/**
 * Copyright (c) 2016-present, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the root directory of this source tree. An additional grant
 * of patent rights can be found in the PATENTS file in the same directory.
 */
namespace Facebook\ShipIt;

final class ShipItSyncPhase extends ShipItPhase {
  private ?string $firstCommit = null;
  private ImmSet<string> $skippedSourceCommits = ImmSet { };
  private ?string $patchesDirectory = null;
  private ?string $statsFilename = null;

  public function __construct(
    private
      (function(
        ShipItBaseConfig,
        ShipItChangeset,
      ): ShipItChangeset) $filter,
    private ImmSet<string> $sourceRoots,
    private ImmSet<string> $destinationRoots = ImmSet { },
  ) {}

  <<__Override>>
  protected function isProjectSpecific(): bool {
    return false;
  }

  <<__Override>>
  public function getReadableName(): string {
    return 'Synchronize commits';
  }

  <<__Override>>
  public function getCLIArguments(): ImmVector<ShipItCLIArgument> {
    return ImmVector {
      shape(
        'long_name' => 'skip-sync-commits',
        'description' => "Don't copy any commits. Handy for testing.\n",
        'write' => $_ ==> $this->skip(),
      ),
      shape(
        'long_name' => 'first-commit::',
        'description' => 'Hash of first commit that needs to be synced',
        'write' => $x ==> $this->firstCommit = $x,
      ),
      shape(
        'long_name' => 'save-patches-to::',
        'description' => 'Directory to copy created patches to. Useful for '.
                         'debugging',
        'write' => $x ==> $this->patchesDirectory = $x,
      ),
      shape(
        'long_name' => 'skip-source-commits::',
        'description' => "Comma-separate list of source commit IDs to skip.",
        'write' =>
          $x ==> {
            $this->skippedSourceCommits = new ImmSet(explode(',', $x));
            foreach ($this->skippedSourceCommits as $commit) {
              // 7 happens to be the usual output
              if (strlen($commit) < ShipItUtil::SHORT_REV_LENGTH) {
                throw new ShipItException(
                  'Skipped rev '.$commit.' is potentially ambiguous; use a '.
                  'longer id instead.'
                );
              }
            }
          },
      ),
      shape(
        'long_name' => 'log-sync-stats-to::',
        'description' => 'The filename to log a JSON-encoded file with stats '.
                         'about the sync.',
        'write' => $x ==> $this->statsFilename = $x,
      ),
    };
  }

  <<__Override>>
  protected function runImpl(
    ShipItBaseConfig $base,
  ): void {
    $sync = (new ShipItSyncConfig($this->sourceRoots, $this->filter))
     ->withDestinationRoots($this->destinationRoots)
     ->withFirstCommit($this->firstCommit)
     ->withSkippedSourceCommits($this->skippedSourceCommits)
     ->withPatchesDirectory($this->patchesDirectory)
     ->withStatsFilename($this->statsFilename);

    (new ShipItSync($base, $sync))->run();
  }
}
