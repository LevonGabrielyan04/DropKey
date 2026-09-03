---
paths:
  - app/Services/R2UploadService.php
---

# Services

## Extensionless R2 object keys
Chat attachment object keys are uploads/{userId}/{ulid} with no file extension. Original filename and extension live only in E2EE attachment metadata (message payload). Download path validation still accepts an optional legacy .{ext} suffix.
