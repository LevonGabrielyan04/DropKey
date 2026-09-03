---
paths:
  - 'resources/views/**/*.blade.php'
---

# Views

## Alpine CSP requires Alpine.data
Livewire csp_safe is enabled. Do not put method shorthand or complex object literals in x-data attributes — Alpine CSP rejects them (CSP Parser Error). Register components with Alpine.data() in resources/js and use x-data="componentName" plus data-* attributes for inputs.
