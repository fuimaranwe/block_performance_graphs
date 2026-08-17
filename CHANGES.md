# Change log

## 0.3.1 - 2026-08-17

- Targeted Moodle 5.2.2 and its bundled Chart.js 4.5.1 API.
- Corrected multi-value radial rings and the class-average line overlay.
- Added theme-aware area gradients and a contained loading overlay.
- Added a localised accessible AJAX error state while preserving the last valid chart.

## 0.3.0 - 2026-08-17

- Replaced the native SVG renderer with Moodle core's MIT-licensed Chart.js integration.
- Added a responsive modern dashboard presentation with tooltips, rounded bars, progress rings and reduced-motion support.
- Preserved the accessible HTML data table and all existing security and AJAX behaviour.

## 0.2.0 - 2026-08-17

- Replaced ApexCharts with a GPL-compatible, dependency-free SVG renderer.
- Enforced block, course, enrolment, grade visibility, group and student access rules.
- Escaped template output and removed JavaScript/HTML injection sinks.
- Corrected line, area, radial and completion-percentage calculations.
- Limited class analytics to authorised active students and visible activities.
- Added required block capabilities, privacy metadata, localisation and regression tests.
