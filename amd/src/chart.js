// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Accessible, dependency-free chart renderer for the Performance Graphs block.
 *
 * @module     block_performance_graphs/chart
 * @copyright  2026 Ahmet Bülbül
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/notification'], function(Notification) {
    'use strict';

    var SVG_NS = 'http://www.w3.org/2000/svg';
    var DEFAULT_COLORS = ['#0f6cbf', '#198754', '#ffc107', '#dc3545', '#6f42c1'];

    var svgElement = function(name, attributes) {
        var element = document.createElementNS(SVG_NS, name);
        Object.keys(attributes || {}).forEach(function(key) {
            element.setAttribute(key, String(attributes[key]));
        });
        return element;
    };

    var appendText = function(parent, text, attributes) {
        var element = svgElement('text', attributes || {});
        element.textContent = String(text);
        parent.appendChild(element);
        return element;
    };

    var normaliseLabel = function(label) {
        return Array.isArray(label) ? label.join(' ') : String(label || '');
    };

    var getLabels = function(options) {
        if (Array.isArray(options.labels)) {
            return options.labels.map(normaliseLabel);
        }
        var categories = options.xaxis && Array.isArray(options.xaxis.categories) ? options.xaxis.categories : [];
        return categories.map(normaliseLabel);
    };

    var getSeries = function(options) {
        if (!Array.isArray(options.series)) {
            return [];
        }
        if (options.series.length && typeof options.series[0] === 'number') {
            return [{name: options.series_name || '', data: options.series}];
        }
        return options.series.map(function(series) {
            return {
                name: series.name || '',
                data: Array.isArray(series.data) ? series.data.map(function(value) {
                    return value === null ? null : Number(value);
                }) : [],
                type: series.type || null
            };
        });
    };

    var getColors = function(options) {
        return Array.isArray(options.colors) && options.colors.length ? options.colors : DEFAULT_COLORS;
    };

    var renderCallout = function(container, options, elementId) {
        var callout = container.parentElement.querySelector('.chart-stat-callout');
        if (!callout) {
            return;
        }
        if (!options._stat_callout) {
            callout.hidden = true;
            return;
        }
        document.getElementById(elementId + '-stat').textContent = options._stat_callout.value;
        document.getElementById(elementId + '-stat-label').textContent = options._stat_callout.label;
        callout.hidden = false;
    };

    var renderTitle = function(container, options) {
        if (!options.title || !options.title.text) {
            return;
        }
        var heading = document.createElement('h4');
        heading.className = 'chart-title';
        heading.textContent = options.title.text;
        container.appendChild(heading);
    };

    var renderNoData = function(container, options) {
        var message = document.createElement('p');
        message.className = 'chart-no-data';
        message.textContent = options.no_data_text || 'No data available';
        container.appendChild(message);
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
        var headrow = document.createElement('tr');
        var labelhead = document.createElement('th');
        labelhead.scope = 'col';
        labelhead.textContent = options.category_label || 'Category';
        headrow.appendChild(labelhead);
        series.forEach(function(item) {
            var cell = document.createElement('th');
            cell.scope = 'col';
            cell.textContent = item.name || options.value_label || 'Value';
            headrow.appendChild(cell);
        });
        head.appendChild(headrow);
        table.appendChild(head);

        var body = document.createElement('tbody');
        labels.forEach(function(label, index) {
            var row = document.createElement('tr');
            var labelcell = document.createElement('th');
            labelcell.scope = 'row';
            labelcell.textContent = label;
            row.appendChild(labelcell);
            series.forEach(function(item) {
                var cell = document.createElement('td');
                cell.textContent = Number.isFinite(item.data[index]) ? item.data[index] : '';
                row.appendChild(cell);
            });
            body.appendChild(row);
        });
        table.appendChild(body);
        details.appendChild(table);
        container.appendChild(details);
    };

    var addAxes = function(svg, max, labels, dimensions) {
        var left = dimensions.left;
        var top = dimensions.top;
        var width = dimensions.width;
        var height = dimensions.height;
        svg.appendChild(svgElement('line', {x1: left, y1: top, x2: left, y2: top + height, stroke: '#6c757d'}));
        svg.appendChild(svgElement('line', {
            x1: left,
            y1: top + height,
            x2: left + width,
            y2: top + height,
            stroke: '#6c757d'
        }));

        [0, 25, 50, 75, 100].forEach(function(percent) {
            var y = top + height - (height * percent / 100);
            svg.appendChild(svgElement('line', {
                x1: left,
                y1: y,
                x2: left + width,
                y2: y,
                stroke: '#e9ecef'
            }));
            appendText(svg, Math.round(max * percent / 100), {
                x: left - 8,
                y: y + 4,
                'text-anchor': 'end',
                'font-size': 11,
                fill: '#495057'
            });
        });

        var step = width / Math.max(labels.length, 1);
        labels.forEach(function(label, index) {
            appendText(svg, label.length > 18 ? label.substring(0, 17) + '…' : label, {
                x: left + step * (index + 0.5),
                y: top + height + 22,
                'text-anchor': 'middle',
                'font-size': 11,
                fill: '#495057'
            });
        });
    };

    var renderCartesian = function(container, labels, series, options, type) {
        var svg = svgElement('svg', {
            viewBox: '0 0 760 390',
            role: 'img',
            'aria-label': options.chart_aria_label || 'Performance chart',
            class: 'performance-graph-svg'
        });
        var dimensions = {left: 55, top: 20, width: 675, height: 310};
        var values = [];
        series.forEach(function(item) {
            values = values.concat(item.data.filter(Number.isFinite));
        });
        var max = Math.max(100, Math.ceil(Math.max.apply(null, values.concat([0])) / 10) * 10);
        addAxes(svg, max, labels, dimensions);
        var colors = getColors(options);
        var step = dimensions.width / Math.max(labels.length, 1);

        if (type === 'bar') {
            var barseries = series.filter(function(item) {
                return item.type !== 'line';
            });
            var barwidth = Math.min(48, step * 0.72 / Math.max(barseries.length, 1));
            barseries.forEach(function(item, seriesindex) {
                item.data.forEach(function(value, index) {
                    if (!Number.isFinite(value)) {
                        return;
                    }
                    var height = dimensions.height * Math.max(0, value) / max;
                    var x = dimensions.left + step * (index + 0.5) -
                        (barwidth * barseries.length / 2) + barwidth * seriesindex;
                    var color = colors[seriesindex % colors.length];
                    if (options.threshold && barseries.length === 1) {
                        color = value >= options.threshold.value ? options.threshold.pass_color : options.threshold.fail_color;
                    }
                    var rect = svgElement('rect', {
                        x: x,
                        y: dimensions.top + dimensions.height - height,
                        width: Math.max(1, barwidth - 2),
                        height: height,
                        fill: color,
                        rx: 4
                    });
                    var title = svgElement('title');
                    title.textContent = labels[index] + ': ' + value;
                    rect.appendChild(title);
                    svg.appendChild(rect);
                });
            });
        }

        series.forEach(function(item, seriesindex) {
            if (type === 'bar' && item.type !== 'line') {
                return;
            }
            var points = [];
            item.data.forEach(function(value, index) {
                if (Number.isFinite(value)) {
                    points.push([
                        dimensions.left + step * (index + 0.5),
                        dimensions.top + dimensions.height - dimensions.height * Math.max(0, value) / max
                    ]);
                }
            });
            if (!points.length) {
                return;
            }
            var pointstring = points.map(function(point) {
                return point.join(',');
            }).join(' ');
            if (type === 'area') {
                var basey = dimensions.top + dimensions.height;
                var polygonpoints = points[0][0] + ',' + basey + ' ' + pointstring + ' ' +
                    points[points.length - 1][0] + ',' + basey;
                svg.appendChild(svgElement('polygon', {
                    points: polygonpoints,
                    fill: colors[seriesindex % colors.length],
                    opacity: 0.2
                }));
            }
            svg.appendChild(svgElement('polyline', {
                points: pointstring,
                fill: 'none',
                stroke: colors[seriesindex % colors.length],
                'stroke-width': 3
            }));
            points.forEach(function(point) {
                svg.appendChild(svgElement('circle', {
                    cx: point[0], cy: point[1], r: 4, fill: colors[seriesindex % colors.length]
                }));
            });
        });
        container.appendChild(svg);
    };

    var polarPoint = function(cx, cy, radius, angle) {
        var radians = (angle - 90) * Math.PI / 180;
        return {x: cx + radius * Math.cos(radians), y: cy + radius * Math.sin(radians)};
    };

    var arcPath = function(cx, cy, radius, startangle, endangle) {
        var start = polarPoint(cx, cy, radius, endangle);
        var end = polarPoint(cx, cy, radius, startangle);
        var largearc = endangle - startangle <= 180 ? 0 : 1;
        return ['M', start.x, start.y, 'A', radius, radius, 0, largearc, 0, end.x, end.y].join(' ');
    };

    var renderPie = function(container, labels, series, options) {
        var values = series[0].data;
        var total = values.reduce(function(sum, value) {
            return sum + Math.max(0, value || 0);
        }, 0);
        if (!total) {
            renderNoData(container, options);
            return;
        }
        var svg = svgElement('svg', {
            viewBox: '0 0 760 360',
            role: 'img',
            'aria-label': options.chart_aria_label || 'Performance chart',
            class: 'performance-graph-svg'
        });
        var colors = getColors(options);
        var angle = 0;
        values.forEach(function(value, index) {
            var sweep = 360 * Math.max(0, value) / total;
            var path;
            if (sweep >= 359.999) {
                path = svgElement('circle', {cx: 250, cy: 170, r: 130, fill: colors[index % colors.length]});
            } else {
                var start = polarPoint(250, 170, 130, angle);
                var end = polarPoint(250, 170, 130, angle + sweep);
                path = svgElement('path', {
                    d: ['M', 250, 170, 'L', start.x, start.y, 'A', 130, 130, 0,
                        sweep > 180 ? 1 : 0, 1, end.x, end.y, 'Z'].join(' '),
                    fill: colors[index % colors.length]
                });
            }
            var title = svgElement('title');
            title.textContent = labels[index] + ': ' + value;
            path.appendChild(title);
            svg.appendChild(path);
            angle += sweep;
        });
        labels.forEach(function(label, index) {
            svg.appendChild(svgElement('rect', {x: 460, y: 80 + index * 28, width: 16, height: 16,
                fill: colors[index % colors.length]}));
            appendText(svg, label + ': ' + values[index], {x: 486, y: 93 + index * 28, 'font-size': 13});
        });
        container.appendChild(svg);
    };

    var renderRadial = function(container, labels, series, options) {
        var values = series[0].data;
        var svg = svgElement('svg', {
            viewBox: '0 0 760 360',
            role: 'img',
            'aria-label': options.chart_aria_label || 'Performance chart',
            class: 'performance-graph-svg'
        });
        var colors = getColors(options);
        values.forEach(function(rawvalue, index) {
            var value = Math.max(0, Math.min(100, Number(rawvalue) || 0));
            var radius = 120 - index * 26;
            svg.appendChild(svgElement('circle', {
                cx: 300, cy: 170, r: radius, fill: 'none', stroke: '#e9ecef', 'stroke-width': 18
            }));
            if (value > 0) {
                svg.appendChild(svgElement('path', {
                    d: arcPath(300, 170, radius, 0, Math.min(359.99, value * 3.6)),
                    fill: 'none', stroke: colors[index % colors.length], 'stroke-width': 18,
                    'stroke-linecap': 'round'
                }));
            }
            appendText(svg, (labels[index] || '') + ': ' + value + '%', {
                x: 470, y: 120 + index * 28, 'font-size': 14
            });
        });
        container.appendChild(svg);
    };

    var render = function(container, options, elementId) {
        container.replaceChildren();
        renderCallout(container, options, elementId);
        renderTitle(container, options);
        var labels = getLabels(options);
        var series = getSeries(options);
        if (!series.length || !series.some(function(item) {
            return item.data.length;
        })) {
            renderNoData(container, options);
            return;
        }
        var type = options.chart && options.chart.type ? options.chart.type : 'bar';
        if (type === 'pie') {
            renderPie(container, labels, series, options);
        } else if (type === 'radialBar') {
            renderRadial(container, labels, series, options);
        } else {
            renderCartesian(container, labels, series, options, type);
        }
        renderTable(container, labels, series, options);
    };

    var setLoading = function(elementId, loading) {
        var loader = document.getElementById(elementId + '-loading');
        if (loader) {
            loader.hidden = !loading;
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

    var init = function(elementId, initialOptions) {
        var container = document.getElementById(elementId);
        if (!container) {
            return;
        }
        var options = typeof initialOptions === 'string' ? JSON.parse(initialOptions) : initialOptions;
        render(container, options || {}, elementId);

        var filters = container.parentElement.querySelector('.chart-filters');
        var courseSelect = document.getElementById(elementId + '-course-select');
        var studentSelect = document.getElementById(elementId + '-student-select');
        if (!filters) {
            return;
        }

        var update = function() {
            setLoading(elementId, true);
            var data = new URLSearchParams({
                sesskey: M.cfg.sesskey,
                blockid: filters.dataset.blockid,
                courseid: courseSelect ? courseSelect.value : filters.dataset.courseid,
                studentid: studentSelect ? studentSelect.value : 0
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
                if (response._students && studentSelect) {
                    var hadSelection = replaceStudents(studentSelect, response._students);
                    if (!hadSelection && studentSelect.options.length) {
                        studentSelect.selectedIndex = 0;
                    }
                }
                render(container, response, elementId);
                setLoading(elementId, false);
            }).catch(function(error) {
                setLoading(elementId, false);
                Notification.exception(error);
            });
        };

        if (courseSelect) {
            courseSelect.addEventListener('change', update);
        }
        if (studentSelect) {
            studentSelect.addEventListener('change', update);
        }
    };

    return {init: init};
});
