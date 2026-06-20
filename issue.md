RUN PHP Lint on local_courseinsights
PHP 8.3.16 | 10 parallel jobs
............ 12/12 (100%)

Checked 12 files in 0.3 seconds
No syntax error found
PS C:\laragon\www\moodle-plugin-ci-main\moodle-plugin-ci-main> vendor\bin\phpcbf.bat --standard=moodle --extensions=php --ignore=_/vendor/_ C:\laragon\www\moodle\local\courseinsights

No fixable errors were found

Time: 2.19 secs; Memory: 16MB

PS C:\laragon\www\moodle-plugin-ci-main\moodle-plugin-ci-main> vendor\bin\phpcs.bat --standard=moodle --extensions=php --ignore=_/vendor/_ C:\laragon\www\moodle\local\courseinsights

## FILE: C:\laragon\www\moodle\local\courseinsights\classes\report_service.php

## FOUND 3 ERRORS AFFECTING 3 LINES

40 | ERROR | Missing docblock for constant report_service::SORT_COLUMNS
57 | ERROR | Missing docblock for constant report_service::COLUMN_TYPES
126 | ERROR | Missing docblock for function get_filters_from_request

---

Time: 1.69 secs; Memory: 16MB

PS C:\laragon\www\moodle-plugin-ci-main\moodle-plugin-ci-main> php bin\moodle-plugin-ci phpdoc C:\laragon\www\moodle\local\courseinsights --moodle C:\laragon\www\moodle --max-warnings=0
RUN Moodle PHPDoc Checker on local_courseinsights
PS C:\laragon\www\moodle-plugin-ci-main\moodle-plugin-ci-main>
PS C:\laragon\www\moodle-plugin-ci-main\moodle-plugin-ci-main> php bin\moodle-plugin-ci validate C:\laragon\www\moodle\local\courseinsights --moodle C:\laragon\www\moodle
RUN Validating local_courseinsights

> Found required file: version.php
> Found required file: lang/en/local_courseinsights.php
> ! Skipping validation of missing or optional file: db/upgrade.php
> In lang/en/local_courseinsights.php, found language pluginname
> In db/install.xml, found table prefixes local_courseinsights
> PS C:\laragon\www\moodle-plugin-ci-main\moodle-plugin-ci-main> php bin\moodle-plugin-ci mustache C:\laragon\www\moodle\local\courseinsights
> RUN Mustache Lint on local_courseinsights
> 'env' is not recognized as an internal or external command,
> operable program or batch file.
> PS C:\laragon\www\moodle-plugin-ci-main\moodle-plugin-ci-main>
