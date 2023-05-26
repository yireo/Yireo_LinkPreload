# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.4.21] - 26 May 2023
### Fixed
- Fix stringing of content (@MaximGns, #43)

## [1.4.20] - 10 May 2023
### Fixed
- Add compatibility with Symfony 6

## [1.4.19] - 5 October 2022
### Fixed
- Added nopush to all preload links

## [1.4.18] - 26 June 2022
### Fixed
- Loosen symfony/dom-crawler constraint #40 (@bramstroker)

## [1.4.17] - 18 June 2022
### Fixed
- Only preload CSS when critical CSS is disabled (@Quazz)

## [1.4.16] - 5 April 2022
### Fixed
- Prevent LinkPreload module on non-HTML content

## [1.4.15] - 31 March 2022
### Fixed
- Don't preload lazy loading images #36 (@Quazz)

## [1.4.14] - 20 February 2022
### Fixed
- Missing crossorigin in html output (@mageho)

## [1.4.13] - 19 January 2022
### Fixed
- Make sure title is only parsed once, fixing issues with SVGs

## [1.4.12] - 8 July 2021
### Fixed
- Fix PHP Fatal Error with preconfigured assets from layout

## [1.4.11] - 7 July 2021
### Fixed
- Prevent multiple rel-attributes
- Complete refactoring to cleanup code

## [1.4.10] - 15 June 2021
### Fixed
- Forget to include XML file

## [1.4.9] - 15 June 2021
### Added
- Added `hyva_default` XML layout handle for Hyva compatibility

## [1.4.8] - 12 January 2021
### Fixed
- Cast response to string #26 (@barryvdh)

## [1.4.7] - 7 January 2021
### Added
- Add preload-attributes to HTML as well

## [1.4.6] - 29 July 2020
### Added
- Magento 2.4 support

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
