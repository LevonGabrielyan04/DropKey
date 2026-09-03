---
paths:
  - app/Services/R2UploadService.php
---

# Services

## Extensionless R2 object keys
Chat attachment object keys are uploads/{userId}/{uuidv7} with no file extension. Original filename and extension live only in E2EE attachment metadata (message payload). Download path validation still accepts legacy ULID keys and an optional .{ext} suffix.
