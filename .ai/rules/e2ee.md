---
paths:
  - 'public/sw.js,resources/js/appBadge.js,resources/js/e2ee/**/*.js'
---

# E2Ee

## PWA home-screen badge via Badging API
Icon badge count uses navigator.setAppBadge/clearAppBadge (iOS 16.4+ installed PWA), not manifest or VAPID. Push payload data.unread_count sets the badge in public/sw.js; chat inbox/session sync from total_unread_messages_count / summed unreadCounts via resources/js/appBadge.js. Always still showNotification on push (Apple user-visible requirement).
