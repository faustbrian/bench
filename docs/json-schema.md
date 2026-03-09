# JSON Schema

`bench` emits a versioned JSON contract. Consumers MUST branch on
`schema_version` and MUST treat unknown fields as additive.

Current schema version:

- `1`

## Top-Level Documents

### Run Report

```json
{
  "results": [],
  "comparison": {
    "rows": [],
    "geometric_mean_reference_gap": 1.0
  },
  "metadata": {
    "schema_version": 1,
    "report_type": "run",
    "generated_at": "2026-03-09T12:00:00+00:00"
  }
}
```

### Comparison Report

```json
{
  "results": [],
  "comparisons": [],
  "reference": {
    "results": []
  },
  "metadata": {
    "schema_version": 1,
    "report_type": "comparison",
    "generated_at": "2026-03-09T12:00:00+00:00"
  }
}
```

## Metadata

When context exists, `metadata` MAY include:

- `environment`
- `settings`
- `selection`
- `current`
- `reference`
- `reference_name`
- `policy`

### Environment

```json
{
  "php_version": "8.5.3",
  "php_sapi": "cli",
  "os_family": "Darwin",
  "architecture": "arm64",
  "extensions": ["json", "mbstring"]
}
```

### Settings

```json
{
  "process_isolation": false,
  "default_iterations": 5,
  "default_revolutions": 1,
  "default_warmup_iterations": 0,
  "significance_enabled": true,
  "significance_alpha": 0.05,
  "significance_minimum_samples": 2
}
```

### Selection

```json
{
  "filter": null,
  "groups": [],
  "competitors": []
}
```

### Policy

Comparison reports MAY include:

```json
{
  "passed": true,
  "violations": []
}
```

## Benchmark Result

Each entry in `results` is a serialized benchmark result:

```json
{
  "subject": "object-transform",
  "scenario": "dto",
  "competitor": "struct",
  "parameters": {},
  "parameter_label": "default",
  "case_label": null,
  "groups": ["dto", "comparison"],
  "summary": {
    "samples": 5,
    "min": 1000.0,
    "max": 1200.0,
    "mean": 1100.0,
    "median": 1080.0,
    "standard_deviation": 30.0,
    "relative_margin_of_error": 1.5,
    "percentile75": 1110.0,
    "percentile95": 1190.0,
    "percentile99": 1198.0,
    "operations_per_second": 925925.92
  },
  "samples": [1050.0, 1080.0, 1100.0],
  "thresholds": [],
  "regression": {
    "metric": "median",
    "tolerance": "5%"
  }
}
```

## Run Comparison Payload

`comparison.rows` summarizes the current run against the configured
comparison reference:

```json
{
  "scenario": "dto",
  "subject": "object-transform",
  "competitor": "struct",
  "parameters": {},
  "winner": "struct",
  "delta_percentage": -12.3,
  "reference_gap": 1.42,
  "reference_gain": 29.6,
  "significance": {
    "status": "significant",
    "label": "significant (p=0.012)",
    "p_value": 0.012,
    "alpha": 0.05,
    "minimum_samples": 2
  }
}
```

## Reference Comparison Rows

`comparisons` in a comparison report aligns current results with a saved
reference:

```json
{
  "scenario": "dto",
  "subject": "object-transform",
  "competitor": "struct",
  "parameter_label": "default",
  "current_median": 1080.0,
  "reference_median": 1210.0,
  "delta_percentage": -10.74,
  "winner": "current",
  "reference_gap": 1.12,
  "reference_gain": 10.74,
  "significance": {
    "status": "significant",
    "label": "significant (p=0.012)",
    "p_value": 0.012,
    "alpha": 0.05,
    "minimum_samples": 2
  },
  "significance_label": "significant (p=0.012)",
  "regression_label": "median @ 5%"
}
```

## Compatibility Rules

- Unknown top-level fields MAY appear in future versions.
- Unknown nested fields MAY appear in future versions.
- `schema_version` changes when a breaking JSON contract change occurs.
- Field order is not significant.
