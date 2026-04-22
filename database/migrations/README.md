# Migrations

The starter schema is maintained as a single file: [`database/schema.sql`](../schema.sql).

Once the project grows, every schema change should be captured as a numbered
migration file here (e.g. `001_add_specialization_to_research.sql`) and applied
in order. A migration runner is not yet included — coordinate with DEV 5
before introducing one.
