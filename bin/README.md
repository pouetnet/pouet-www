# Maintainers tools

Both scripts need `ssh pouet` access to the live server, so only the
maintainers can run them. Run them from the repo root.

- `dump_DDL` dumps the live database schema into `pouet.sql`, stripped of
    runtime details (auto-increment counters, dump headers). Commit the
    result with any change that alters the schema.
- `dump_SQL_for_dev` builds the sample data dump used by the local stack,
    in `contribs/pouet_with_sample_data.sql`: public tables in full, private
    tables empty, and only the rows of the users who agreed to be in it.
    It is not scrubbed of personal data yet, so don't share it.
