# MediaPitch Store

Affiliate product-discovery and editorial buying-guide platform for MediaPitch.

This repository implements the architecture described in the project brief: products are stored in a central database and can originate from manual entry, Amazon Creators API, or API data with editorial overrides. The public frontend consumes the same internal product model regardless of source.

## Phase 1 goals

- PHP application with MariaDB
- MediaPitch-branded shopping/discovery frontend
- Product and category CMS foundations
- Buying guides and blog content types
- Affiliate-link redirect/click tracking
- SEO-friendly routes and metadata
- Amazon settings/API adapter boundary without making API access a launch dependency

## Local setup

1. Copy `.env.example` to `.env`.
2. Add your MariaDB connection values.
3. Import `database/schema.sql`.
4. Point the web server document root to `public/`.
5. Ensure PHP has the PDO MySQL extension enabled.

The project intentionally starts without third-party PHP dependencies so it can run on a standard PHP hosting environment. Additional packages can be introduced later where they materially improve maintainability.
