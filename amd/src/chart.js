// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Chart.js renderer for the Performance Graphs block.
 * @module     block_performance_graphs/chart
 * @copyright  2026 Ahmet Bülbül
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/chartjs', 'core/notification'], function(Chart, Notification) {
    'use strict';

    var instances = Object.create(null);
    var defaultColors = ['#0f6cbf', '#198754', '#ffc107', '#dc3545', '#6f42c1'];
    var centerTextPlugin = {
        id: 'performanceGraphsCenterText',
        afterDraw: function(chart) {
            if (chart.config.type !== 'doughnut' || chart.data.datasets.length !== 1 ||
                    !chart.data.datasets[0].data.length) {
                return;
            }
            var value = chart.data.datasets[0].data[0];
            var area = chart.chartArea;
            var context = chart.ctx;
            context.save();
            context.fillStyle = window.getComputedStyle(chart.canvas).color || '#212529';
            context.font = '600 1.7rem ' + (window.getComputedStyle(chart.canvas).fontFamily || 'sans-serif');
            context.textAlign = 'center';
            context.textBaseline = 'middle';
            context.fillText(Math.round(value) + '%', (area.left + area.right) / 2, (area.top + area.bottom) / 2);
            context.restore();
        }
    };
    var getLabels = function(options) {
        var labels = Array.isArray(options.labels) ? options.labels :
            (options.xaxis && Array.isArray(options.xaxis.categories) ? options.xaxis.categories : []);
        return labels.map(function(label) { return Array.isArray(label) ? label.join(' ') : String(label || ''); });
    };
    var getSeries = function(options) {
        if (!Array.isArray(options.series)) { return []; }
        if (options.series.length && typeof options.series[0] === 'number') {
            return [{name: options.series_name || '', data: options.series}];
        }
        return options.series.map(function(series) {
            return {name: series.name || '', data: Array.isArray(series.data) ? series.data.map(function(value) {
                return value === null ? null : Number(value);
            }) : [], type: series.type || null};
        });
    };
    var getColors = function(options) {
        return Array.isArray(options.colors) && options.colors.length ? options.colors : defaultColors;
    };
    var destroy = function(id) {
        if (instances[id]) { instances[id].destroy(); delete instances[id]; }
    };
    var theme = function(container) {
        var style = window.getComputedStyle(container);
        return {text: style.getPropertyValue('--bs-body-color').trim() || '#212529',
            muted: style.getPropertyValue('--bs-secondary-color').trim() || '#6c757d',
            grid: style.getPropertyValue('--bs-border-color').trim() || '#dee2e6',
            font: style.fontFamily || 'sans-serif'};
    };
    var renderCallout = function(container, options, id) {
        var callout = container.parentElement.querySelector('.chart-stat-callout');
        if (!callout) {
            return;
        }
        if (!options._stat_callout) {
            callout.hidden = true;
            return;
        }
        document.getElementById(id + '-stat').textContent = options._stat_callout.value;
        document.getElementById(id + '-stat-label').textContent = options._stat_callout.label;
        callout.hidden = false;
    };
    var renderTable = function(container, labels, series, options) {
        var details = document.createElement('details');
        details.className = 'chart-data';
        var summary = document.createElement('summary');
        summary.textContent = options.table_summary || 'View chart data';
        details.appendChild(summary);
        var table = document.createElement('table');
        table.className = 'table table-sm';
        var caption = document.createElement('caption');
        caption.textContent = options.table_caption || 'Chart data';
        table.appendChild(caption);
        var head = document.createElement('thead');
        var row = document.createElement('tr');
        var label = document.createElement('th');
        label.scope = 'col';
        label.textContent = options.category_label || 'Category';
        row.appendChild(label);
        series.forEach(function(item) {
            var cell = document.createElement('th');
            cell.scope = 'col';
            cell.textContent = item.name || options.value_label || 'Value';
            row.appendChild(cell);
        });
        head.appendChild(row);
        table.appendChild(head);
        var body = document.createElement('tbody');
        labels.forEach(function(labeltext, index) {
            var bodyrow = document.createElement('tr');
            var labelcell = document.createElement('th');
            labelcell.scope = 'row';
            labelcell.textContent = labeltext;
            bodyrow.appendChild(labelcell);
            series.forEach(function(item) {
                var cell = document.createElement('td');
                cell.textContent = Number.isFinite(item.data[index]) ? item.data[index] : '';
                bodyrow.appendChild(cell);
            });
            body.appendChild(bodyrow);
        });
        table.appendChild(body);
        details.appendChild(table);
        container.appendChild(details);
    };
    var areaFill = function(color) {
        return function(context) {
            var chart = context.chart;
            if (!chart.chartArea) {
                return color + '33';
            }
            var gradient = chart.ctx.createLinearGradient(0, chart.chartArea.top, 0, chart.chartArea.bottom);
            gradient.addColorStop(0, color + '55');
            gradient.addColorStop(1, color + '05');
            return gradient;
        };
    };
    var datasets = function(type, labels, series, options, colors) {
        if (type === 'pie') {
            return [{
                label: series[0].name,
                data: series[0].data,
                backgroundColor: labels.map(function(unused, index) {
                    return colors[index % colors.length];
                }),
                borderColor: 'transparent',
                borderWidth: 3,
                hoverOffset: 8
            }];
        }
        var barSeriesCount = series.filter(function(item) {
            return item.type !== 'line';
        }).length;
        return series.map(function(item, seriesIndex) {
            var color = colors[seriesIndex % colors.length];
            var line = type !== 'bar' || item.type === 'line';
            var points = item.data.map(function(value) {
                if (options.threshold && type === 'bar' && item.type !== 'line' &&
                        barSeriesCount === 1 && Number.isFinite(value)) {
                    return value >= options.threshold.value ? options.threshold.pass_color : options.threshold.fail_color;
                }
                return color;
            });
            return {
                label: item.name,
                data: item.data,
                type: item.type || undefined,
                backgroundColor: line ? (type === 'area' && !item.type ? areaFill(color) : color) : points,
                borderColor: line ? color : points,
                borderWidth: line ? 3 : 0,
                borderRadius: type === 'bar' ? 7 : 0,
                borderSkipped: false,
                pointRadius: line ? 4 : 0,
                pointHoverRadius: line ? 6 : 0,
                pointBackgroundColor: points,
                tension: type === 'line' || type === 'area' ? 0.35 : 0,
                fill: type === 'area' && !item.type,
                spanGaps: true,
                order: item.type === 'line' ? 0 : 1
            };
        });
    };
    var config = function(container, options, labels, series) {
        var requested = options.chart && options.chart.type ? options.chart.type : 'bar';
        var type = requested === 'radialBar' ? 'doughnut' : requested;
        var colors = getColors(options);
        var colorset = theme(container);
        var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var result = {
            type: type,
            data: {
                labels: labels,
                datasets: datasets(requested === 'radialBar' ? 'pie' : requested,
                    labels, series, options, colors)
            },
            plugins: [centerTextPlugin],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                normalized: true,
                animation: reduced ? false : {duration: 650, easing: 'easeOutQuart'},
                plugins: {
                    legend: {
                        display: requested !== 'bar' || series.length > 1,
                        position: 'bottom',
                        labels: {
                            color: colorset.text,
                            usePointStyle: true,
                            padding: 18,
                            font: {family: colorset.font}
                        }
                    },
                    tooltip: {enabled: true, intersect: false, mode: 'index'}
                },
                scales: type === 'doughnut' || type === 'pie' ? {} : {
                    x: {
                        ticks: {
                            color: colorset.muted,
                            maxRotation: 0,
                            autoSkip: true,
                            font: {family: colorset.font}
                        },
                        grid: {display: false}
                    },
                    y: {
                        beginAtZero: true,
                        suggestedMax: 100,
                        ticks: {color: colorset.muted, font: {family: colorset.font}},
                        grid: {color: colorset.grid}
                    }
                }
            }
        };
        if (requested === 'radialBar') {
            result.options.cutout = '72%';
            result.options.rotation = -90;
            result.options.circumference = 360;
            var values = series.length === 1 ? series[0].data : series.map(function(item) {
                return item.data[0];
            });
            result.data.labels = ['', ''];
            result.data.datasets = values.map(function(rawValue, i) {
                var value = Math.max(0, Math.min(100, Number(rawValue) || 0));
                return {
                    label: labels[i] || series[0].name,
                    data: [value, 100 - value],
                    backgroundColor: [colors[i % colors.length], colorset.grid],
                    borderWidth: 0,
                    weight: 1
                };
            });
            result.options.plugins.legend.display = false;
            result.options.plugins.tooltip.filter = function(context) {
                return context.dataIndex === 0;
            };
            result.options.plugins.tooltip.callbacks = {label: function(context) {
                return context.dataset.label + ': ' + context.raw + '%';
            }};
        }
        return result;
    };
    var render = function(container, options, id) {
        destroy(id);
        container.replaceChildren();
        renderCallout(container, options, id);
        var labels = getLabels(options);
        var series = getSeries(options);
        if (!series.length || !series.some(function(item) {
            return item.data.length;
        })) {
            var empty = document.createElement('p');
            empty.className = 'chart-no-data';
            empty.textContent = options.no_data_text || 'No data available';
            container.appendChild(empty);
            return;
        }
        var title = document.createElement('h4');
        title.className = 'chart-title';
        title.textContent = options.title && options.title.text ? options.title.text : '';
        title.hidden = !title.textContent;
        container.appendChild(title);
        var stage = document.createElement('div');
        stage.className = 'performance-graph-stage';
        var canvas = document.createElement('canvas');
        canvas.className = 'performance-graph-canvas';
        canvas.setAttribute('role', 'img');
        canvas.setAttribute('aria-label', options.chart_aria_label || 'Performance chart');
        stage.appendChild(canvas);
        container.appendChild(stage);
        instances[id] = new Chart(canvas.getContext('2d'), config(container, options, labels, series));
        renderTable(container, labels, series, options);
    };
    var loading = function(id, value) {
        var loader = document.getElementById(id + '-loading');
        var container = document.getElementById(id);
        if (loader) {
            loader.hidden = !value;
        }
        if (container) {
            container.setAttribute('aria-busy', value ? 'true' : 'false');
        }
    };
    var showError = function(id, value) {
        var error = document.getElementById(id + '-error');
        if (error) {
            error.hidden = !value;
        }
    };
    var replaceStudents = function(select, students) {
        var current = select.value;
        select.replaceChildren();
        students.forEach(function(student) {
            var option = document.createElement('option');
            option.value = student.id;
            option.textContent = student.name;
            option.selected = String(student.id) === String(current);
            select.appendChild(option);
        });
        return Array.from(select.options).some(function(option) {
            return option.selected;
        });
    };
    var init = function(id, initialOptions) {
        var container = document.getElementById(id);
        if (!container) {
            return;
        }
        var options = typeof initialOptions === 'string' ? JSON.parse(initialOptions) : initialOptions;
        render(container, options || {}, id);
        var card = container.closest('.block-performance-graphs-card');
        var filters = card ? card.querySelector('.chart-filters') : null;
        var course = document.getElementById(id + '-course-select');
        var student = document.getElementById(id + '-student-select');
        if (!filters) {
            return;
        }
        var update = function() {
            loading(id, true);
            showError(id, false);
            var data = new URLSearchParams({
                sesskey: M.cfg.sesskey,
                blockid: filters.dataset.blockid,
                courseid: course ? course.value : filters.dataset.courseid,
                studentid: student ? student.value : 0
            });
            fetch(M.cfg.wwwroot + '/blocks/performance_graphs/ajax.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: data.toString()
            }).then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            }).then(function(response) {
                if (response.error) {
                    throw new Error(response.message || 'Unable to load chart data');
                }
                if (response._students && student) {
                    if (!replaceStudents(student, response._students) && student.options.length) {
                        student.selectedIndex = 0;
                    }
                }
                render(container, response, id);
                loading(id, false);
            }).catch(function(error) {
                loading(id, false);
                showError(id, true);
                Notification.exception(error);
            });
        };
        if (course) {
            course.addEventListener('change', update);
        }
        if (student) {
            student.addEventListener('change', update);
        }
    };
    return {init: init};
});
