# Change log

## [Unreleased]

### Changed
* `relationalFields` now protected property. Use `relations` property instead. **Break BC.**

### Added
* `relations` property may contain callback function to adjust creation of relation models.
* Integration test for update models with callback function.
* Change log.