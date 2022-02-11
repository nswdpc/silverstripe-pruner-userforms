# Userforms pruner

Userforms extension to `nswdpc/silverstripe-pruner`, to remove submitted form records after a configured time period.


## Install

```shell
composer require nswdpc/silverstripe-pruner-userforms
```

## Using

1. Create a `NSWDPC\Pruner\PruneJob` queued job with the following constructor arguments:
    1. days_ago (float, remove records older than this number of days)
    1. limit (int, limit records in this operation to this number)
    1. targets (string `SilverStripe\UserForms\Model\Submission\SubmittedForm`)
    1. report_only (1|0, set to 1 to run the job in report only mode, nothing is removed)
1. Run the job

## Limits

As `SubmittedForm` has a [polymorphic has_one parent](https://docs.silverstripe.org/en/4/developer_guides/model/relations/#polymorphic-has-one), the limit parameter specified is used per parent class. If you have specified a limit of 50 and have multiple parent classes for the SubmittedForm model, that limit will be set per parent.

This will be the case if you have [dnadesign/silverstripe-elemental-userforms](https://github.com/dnadesign/silverstripe-elemental-userforms) active alongside [silverstripe/userforms](https://github.com/silverstripe/silverstripe-userforms).

## Maintainers

+ [dpcdigital@NSWDPC:~$](https://dpc.nsw.gov.au)

## License

[BSD-3-Clause](./LICENSE.md)

## Security

If you have found a security issue with this module, please email digital[@]dpc.nsw.gov.au in the first instance, detailing your findings.

## Bugtracker

We welcome bug reports, pull requests and feature requests on the Github Issue tracker for this project.

Please review the [code of conduct](./code-of-conduct.md) prior to opening a new issue.

## Development and contribution

If you would like to make contributions to the module please ensure you raise a pull request and discuss with the module maintainers.

Please review the [code of conduct](./code-of-conduct.md) prior to completing a pull request.
