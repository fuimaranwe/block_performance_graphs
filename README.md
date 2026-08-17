# Moodle Performance Graphs Block Plugin (`block_performance_graphs`)

An interactive performance analytics and visualization block plugin for Moodle 5.2+. It renders real-time, customisable charts for class-wide progress and individual student performance using Moodle's bundled Chart.js integration.

---

## Features

- **Multiple Chart Types**:
  - **Bar / Column Chart**: Clear bars with optional passing-threshold highlighting (red for failing, green for passing).
  - **Pie Chart**: Clear breakdown of completion and progress status.
  - **Radial Bar Chart**: Circular progress meters for course activity completion.
  - **Line / Area Chart**: Ordered quiz and assignment performance comparisons.

- **Dual Viewing Modes**:
  - **Class Mode** *(Teachers & Admins)*:
    - Overall Course Completion Rates.
    - Class Average Quiz Scores across all enrolled students.
  - **Student Mode** *(Students & Teachers)*:
    - Activity Completion Progress.
    - Individual Assignment & Quiz Scores.
    - **Class Average Overlay**: Compare individual scores against the overall class average line.

- **Dynamic Filtering**: Select courses and students directly within the block without page reloads.

- **Responsive & Accessible**: Interactive Chart.js charts include ARIA labels, reduced-motion support and an expandable data table.

---

## Requirements

- **Moodle Version**: Moodle 5.2.0 (2026042000) or any later 5.2.x release.
- **PHP Version**: PHP 8.3 or PHP 8.4, matching Moodle 5.2 requirements.
- **Browser**: Modern web browser with JavaScript enabled.

Release `0.3.2` supports the Moodle 5.2.x branch and uses its bundled Chart.js 4.5.1 integration. No CDN request or duplicate Chart.js copy is required.

---

## Installation

1. **Download / Clone**:
   Clone or extract this repository into your Moodle installation's `public/blocks/` directory:
   ```bash
   cd /path/to/moodle/public/blocks
   git clone https://github.com/fuimaranwe/block_performance_graphs.git performance_graphs
   ```
   *(Ensure the directory name is `performance_graphs`)*

2. **Run Moodle Upgrade**:
   - Log in to your Moodle site as an Administrator.
   - Go to **Site Administration > Notifications**.
   - Complete the database upgrade prompt for `block_performance_graphs`.
   - Purge Moodle caches so the updated AMD module and styles are loaded.

3. **Add Block to a Page**:
   - Turn editing on on your Moodle Course page or Dashboard.
   - Click **Add a block** and select **Performance Graphs**.

---

## Configuration

Edit the block instance settings to customize the display:

| Option | Description |
| :--- | :--- |
| **Target Mode** | Choose between `Class Level` (Overall) or `Student Level` (Individual). |
| **Chart Type** | Select `Bar`, `Pie`, `Radial Bar`, `Line`, or `Area`. |
| **Metric** | Choose between Completion Rates or Quiz/Assignment Scores. |
| **Chart Colour** | Choose the primary chart colour. |
| **Passing Threshold** | Highlight scores dynamically: values below the threshold render in red and passing grades in green. |
| **Show Class Average** | Overlay class average trend line on student score charts. |

---

## Directory Structure

```
block_performance_graphs/
├── ajax.php                 # AJAX endpoint for live chart filter updates
├── block_performance_graphs.php # Main block class implementation
├── edit_form.php            # Block configuration form definition
├── styles.css               # Block and chart presentation
├── version.php             # Moodle plugin metadata and version requirements
├── amd/                    # AMD Chart.js adapter and generated module
│   ├── src/
│   └── build/
├── classes/
│   ├── data_provider.php    # Data querying & chart payload formatter
│   └── privacy/provider.php # Moodle GDPR privacy API compliance provider
├── db/
│   └── access.php           # Block capabilities
├── lang/
│   └── en/                 # English language strings
├── pix/                    # Block icon (SVG)
├── tests/                  # PHPUnit regression tests
├── CHANGES.md              # Release history
├── LICENSE                 # GPL licensing notice
└── templates/
    └── chart.mustache       # Mustache template for chart container
```

---

## Privacy & GDPR

This plugin implements Moodle's Privacy API (`\core_privacy\local\metadata\null_provider`). It reads existing grade and completion data but does not store personal data in plugin tables or external services.

Grade data is restricted using Moodle course access, enrolment, capability, hidden-grade and separate-group rules.

The block stores no additional grade, completion or user records.

---

## Development

Run the available checks from the plugin directory:

```bash
php -l ajax.php
node --check amd/src/chart.js
git diff --check
```

The PHPUnit tests require a configured Moodle checkout and are located in `tests/`.

---

## Contributing

Contributions, bug reports, and feature requests are welcome. Feel free to open an issue or submit a Pull Request on [GitHub](https://github.com/fuimaranwe/block_performance_graphs).

---

## License

This plugin is licensed under the [GNU General Public License v3.0](https://www.gnu.org/licenses/gpl-3.0.html) or later.

Chart rendering uses the Chart.js library supplied by Moodle core. Chart.js is licensed under the [MIT License](https://github.com/chartjs/Chart.js/blob/master/LICENSE.md); this plugin does not redistribute a separate Chart.js copy.
