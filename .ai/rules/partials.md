---
paths:
  - 'resources/js/pwaInstallPrompt.js,resources/views/partials/pwa-install-prompt.blade.php'
---

# Partials

## PWA install offer first two visits
Offer PWA install only on the first 2 browser sessions (sessionStorage gates counting; localStorage stores visit count + dismissal). Hide when already installed or dismissed. Keep the banner markup in partials/pwa-install-prompt.blade.php and drive visibility from pwaInstallPrompt.js without Alpine.
