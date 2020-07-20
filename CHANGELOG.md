# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.4.5] - 2020-07-20
### Added
- Increase dep with symfony/css-selector

## [1.4.4] - 2020-06-12
### Added
- Construct link according to W3 specs, including crossorigin on fonts (@dimitri-koenig)

## [1.4.3] - 2020-06-03
### Added
- Extends layout configuration with options for fonts, images and styles (@dimitri-koenig)

## [1.4.2] - 2020-02-17
### Fixed
- If link is full URL, try to turn it into absolute path

## [1.4.1] - 2019-09-10
### Fixed
- Prevent HTTP/HTTPS URLs to be turned into static content URLs

## [1.4.0] - 2019-09-08
### Added
- Add better detection of stylesheets (rel=stylesheet instead of as=style)
- Add XML layout instructions for custom additional links to be set
- Increase requirement for framework 102 or higher

## [1.3.0] - 2019-05-03
### Added
- Add a flag "Skip Images" 
- Skip image when Yireo Webp2 is installed

## [1.2.0] - 2019-04-23
### Changed
- Changed name of module from `ServerPush` to `LinkPreload`

## [1.1.0] - 2019-04-19
### Added
- Add a separate `Config` class
- Add a CHANGELOG

### Changed
- Changed paths in System Configuration
- Make cookie check optional, because it only *might* be needed with Varnish
- Remove frontend-check, so it also works for backend :)

### Removed
- Make several `protected` methods `private`

## [1.0.0] - 2019-03
### Added
- Add a cookie to stop serving `Link` headers after initial request

## [0.0.1] - 2018
### Added
- Initial release
