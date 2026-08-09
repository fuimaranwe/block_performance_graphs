define('block_performance_graphs/chart', ['jquery', 'block_performance_graphs/apexcharts'], function($, ApexChartsModule) {
    return {
        init: function(elementId, config) {
            var options = JSON.parse(config);
            var ChartClass = typeof ApexChartsModule !== 'undefined' ? ApexChartsModule : window.ApexCharts;
            var container = document.querySelector("#" + elementId);
            var skeleton = document.getElementById(elementId + "-skeleton");
            
            function renderStatCallout(opts) {
                var calloutContainer = container.parentElement.querySelector('.chart-stat-callout');
                if (opts._stat_callout) {
                    if (calloutContainer) {
                        calloutContainer.style.display = 'block';
                        document.getElementById(elementId + '-stat').innerText = opts._stat_callout.value;
                        document.getElementById(elementId + '-stat-label').innerText = opts._stat_callout.label;
                    }
                } else if (calloutContainer) {
                    calloutContainer.style.display = 'none';
                }
            }
            
            renderStatCallout(options);
            container.innerHTML = '';
            var chart = new ChartClass(container, options);
            if (skeleton) {
                skeleton.style.display = 'none';
            }
            chart.render();
            
            var courseSelect = document.getElementById(elementId + '-course-select');
            var studentSelect = document.getElementById(elementId + '-student-select');
            
            function handleFilterChange() {
                var filtersContainer = container.parentElement.querySelector('.chart-filters');
                if (!filtersContainer) return;
                
                var blockid = filtersContainer.getAttribute('data-blockid');
                var sesskey = filtersContainer.getAttribute('data-sesskey');
                var courseid = courseSelect ? courseSelect.value : 0;
                var studentid = studentSelect ? studentSelect.value : 0;
                
                if (skeleton) {
                    skeleton.style.display = 'flex';
                }
                
                $.ajax({
                    url: M.cfg.wwwroot + '/blocks/performance_graphs/ajax.php',
                    type: 'GET',
                    data: {
                        sesskey: sesskey,
                        blockid: blockid,
                        courseid: courseid,
                        studentid: studentid
                    },
                    dataType: 'json',
                    success: function(response) {
                        renderStatCallout(response);
                        
                        if (response._students && studentSelect) {
                            var currentVal = studentSelect.value;
                            studentSelect.innerHTML = '';
                            var hasMatch = false;
                            response._students.forEach(function(s) {
                                var option = document.createElement('option');
                                option.value = s.id;
                                option.innerHTML = s.name;
                                if (s.id == currentVal) {
                                    option.selected = true;
                                    hasMatch = true;
                                }
                                studentSelect.appendChild(option);
                            });
                            
                            if (!hasMatch && response._students.length > 0) {
                                studentSelect.value = response._students[0].id;
                                handleFilterChange();
                                return;
                            }
                        }
                        
                        chart.updateOptions(response);
                        
                        if (skeleton) {
                            skeleton.style.display = 'none';
                        }
                    },
                    error: function(err) {
                        console.error('Failed to fetch chart data', err);
                        if (skeleton) {
                            skeleton.style.display = 'none';
                        }
                    }
                });
            }
            
            if (courseSelect) {
                courseSelect.addEventListener('change', handleFilterChange);
            }
            if (studentSelect) {
                studentSelect.addEventListener('change', handleFilterChange);
            }
        }
    };
});
