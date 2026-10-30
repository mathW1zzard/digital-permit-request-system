# Shared validation rules

The server is the authority. The Flutter form applies the same rules so users see errors before submitting. Keep this table in step with both codebases.

| Field | Rule (server and client) |
| --- | --- |
| `permit_type_id` | Required; must be an existing type |
| `purpose` | Required; 10-255 characters |
| `location` | Required; at most 120 characters |
| `details` | Optional; at most 2000 characters |
| `requested_start` | Required; format YYYY-MM-DD; today or later |
| `comment` (revision), `reason` (rejection) | Required; 5-500 characters |
