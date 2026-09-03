---
paths:
  - 'public/sw.js,resources/js/registerServiceWorker.js,app/Http/Controllers/WebAppManifestController.php'
---

# Controllers

## PWA is install-only, not offline
PassShare is an installable PWA (manifest + SW + icons) but must not cache chat, sends, Livewire, or secret payloads. Keep public/sw.js network-only for fetch; extend push handlers in the same file. Register via resources/js/registerServiceWorker.js on load so push and PWA share /sw.js.
