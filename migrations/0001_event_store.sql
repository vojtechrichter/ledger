CREATE TABLE events (
    global_position BIGSERIAL PRIMARY KEY,
    stream_id       UUID        NOT NULL,
    stream_version  INT         NOT NULL,
    event_type      TEXT        NOT NULL,
    event_version   SMALLINT    NOT NULL DEFAULT 1,
    payload         JSONB       NOT NULL,
    metadata        JSONB       NOT NULL,
    occurred_at     TIMESTAMPTZ NOT NULL,
    UNIQUE (stream_id, stream_version)
);

CREATE TABLE snapshots (
    stream_id      UUID  PRIMARY KEY,
    stream_version INT   NOT NULL,
    state          JSONB NOT NULL
);

CREATE TABLE outbox (
    id                    BIGSERIAL   PRIMARY KEY,
    event_global_position BIGINT      NOT NULL REFERENCES events (global_position),
    published_at          TIMESTAMPTZ NULL
);

CREATE INDEX outbox_unpublished_idx ON outbox (id) WHERE published_at IS NULL;
