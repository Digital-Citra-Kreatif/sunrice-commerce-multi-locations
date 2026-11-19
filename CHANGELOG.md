# Changelog

## Unreleased

- Locations (warehouses, shops, pickup points) with stock per location, kept in step with each
  variant's total.
- Deliveries quoted from, and stock taken at, the first location that has every item; deliveries no
  single location can send are refused (configurable).
- Local pickup at the locations marked for it, with the stock taken there.
- Cancellations and refunds give stock back where it came from.
- Locations → Stock (Adjust, Transfer) and Movements; `locations:place-stock`.
- `Fulfilment::resolveUsing()` for extensions that place lines at a location (rentals), and
  `DeletionGuards` for those whose records point at one.
