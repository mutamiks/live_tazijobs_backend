# Admin Worker Directory

The admin worker directory is available at `/ADMIN/workers-directory`. Approved employers use `/app/workers`; both views show the same skilled and unskilled separation.

## API contract

The frontend calls `GET /api/admin/workers-directory` with the authenticated admin token. The endpoint is protected by the `access_admin` middleware group and requires either `search_workers` or `approve_job_seekers`.

Only job seeker profiles that are approved, available, and attached to an approved user are returned. The response has two arrays:

```json
{
  "skilled_workers": [],
  "unskilled_workers": []
}
```

Each profile from both endpoints includes `classification`, with one of these values:

- `skilled`: education contains university, tertiary, diploma, degree, or postgraduate.
- `unskilled`: all other education values, including an empty value.

The profile fields used by the directory are `id`, `full_name`, `job_title`, and `education_level`.
