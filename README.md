# Moodle Performance Graphs Block Plugin (`block_performance_graphs`)

An interactive, modern performance analytics and visualization block plugin for Moodle 4.0+. It renders real-time, customizable charts for class-wide progress and individual student performance using **ApexCharts**.

---

## 📊 Features

- **Multiple Chart Types**:
  - 📊 **Bar / Column Chart**: Modern gradient bars with optional passing threshold highlighting (red for failing, green for passing).
  - 🍩 **Pie / Donut Chart**: Clear breakdown of completion and progress status.
  - 🎯 **Radial Bar Chart**: Sleek circular progress meters for course activity completion.
  - 📈 **Line / Area Chart**: Smooth trend tracking for assignment and quiz performance over time.

- **Dual Viewing Modes**:
  - **Class Mode** *(Teachers & Admins)*:
    - Overall Course Completion Rates.
    - Class Average Quiz Scores across all enrolled students.
  - **Student Mode** *(Students & Teachers)*:
    - Activity Completion Progress.
    - Individual Assignment & Quiz Scores.
    - **Class Average Overlay**: Compare individual scores against the overall class average line.

- **Dynamic AJAX Filtering**: Select courses, students, and metric types directly within the block with zero page reloads.

- **Fully Responsive & Animated**: Powered by ApexCharts with smooth micro-animations and responsive layout adaptation.

---

## ⚙️ Requirements

- **Moodle Version**: Moodle 4.0 (2022041900) or higher.
- **PHP Version**: PHP 7.4 or PHP 8.x.
- **Browser**: Modern web browser with JavaScript enabled.

---

## 🚀 Installation

1. **Download / Clone**:
   Clone or extract this repository into your Moodle installation's `blocks/` directory:
   ```bash
   cd /path/to/moodle/blocks
   git clone https://github.com/fuimaranwe/block_performance_graphs.git performance_graphs
   ```
   *(Ensure the directory name is `performance_graphs`)*

2. **Run Moodle Upgrade**:
   - Log in to your Moodle site as an Administrator.
   - Go to **Site Administration > Notifications**.
   - Complete the database upgrade prompt for `block_performance_graphs`.

3. **Add Block to a Page**:
   - Turn editing on on your Moodle Course page or Dashboard.
   - Click **Add a block** and select **Performance Graphs**.

---

## 🛠️ Configuration

Edit the block instance settings to customize the display:

| Option | Description |
| :--- | :--- |
| **Target Mode** | Choose between `Class Level` (Overall) or `Student Level` (Individual). |
| **Chart Type** | Select `Bar`, `Pie`, `Radial Bar`, `Line`, or `Area`. |
| **Metric** | Choose between Completion Rates or Quiz/Assignment Scores. |
| **Chart Color** | Set your preferred primary color hex code (e.g. `#008FFB`). |
| **Passing Threshold** | Highlight scores dynamically: values below threshold render in red (`#FF4560`) and passing grades in green (`#00E396`). |
| **Show Class Average** | Overlay class average trend line on student score charts. |

---

## 📁 Directory Structure

```
block_performance_graphs/
├── ajax.php                 # AJAX endpoint for live chart filter updates
├── block_performance_graphs.php # Main block class implementation
├── edit_form.php            # Block configuration form definition
├── version.php             # Moodle plugin metadata and version requirements
├── amd/                    # AMD JS modules (ApexCharts loader & renderer)
│   ├── src/
│   └── build/
├── classes/
│   ├── data_provider.php    # Data querying & chart payload formatter
│   └── privacy/provider.php # Moodle GDPR privacy API compliance provider
├── lang/
│   └── en/                 # English language strings
├── pix/                    # Block icon (SVG)
└── templates/
    └── chart.mustache       # Mustache template for chart container
```

---

## 🔒 Privacy & GDPR

This plugin implements Moodle's Privacy API (`\core_privacy\local\metadata\null_provider`), confirming that it reads grade and completion data to display graphs but stores no personal data directly in additional plugin database tables.

---

## 🤝 Contributing

Contributions, bug reports, and feature requests are welcome! Feel free to open an issue or submit a Pull Request on [GitHub](https://github.com/fuimaranwe/block_performance_graphs).

---

## 📄 License

This plugin is licensed under the [GNU General Public License v3.0](https://www.gnu.org/licenses/gpl-3.0.html) or later.
