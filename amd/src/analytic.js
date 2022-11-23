define(['jquery', 'core/ajax'], function ($, ajax) {
    return {
        analytic: function () {
            var lastAttemptSummary, loggedInUser, mixChart, allUsers, questionPerCategories, timeChart, gradeAnalysis, quesAnalysis, hardestQuestions, allQuestions, quizid, rooturl, userid, lastUserQuizAttemptID;
            var attemptsSnapshotArray = [];
            Chart.plugins.register({
                beforeDraw: function (chartInstance) {
                    var chartConvention = chartInstance.chart.ctx;
                    chartConvention.fillStyle = "white";
                    chartConvention.fillRect(0, 0, chartInstance.chart.width, chartInstance.chart.height);
                }
            });
            $(".viewanalytic").click(function () {
                var quizid = $(this).data('quiz_id');
                var promises = ajax.call([
                    {
                        methodname: 'moodle_quizanalytics_analytic',
                        args: { quizid: quizid },
                    }
                ]);
                promises[0].done(function (data) {
                    var totalData = jQuery.parseJSON(data);
                    if (totalData) {    
                        allQuestions = totalData.allQuestions;
                        quizid = totalData.quizid;
                        rooturl = totalData.url;
                        lastUserQuizAttemptID = totalData.lastUserQuizAttemptID;
                        $(".showanalytics").find(".parentTabs").find("span.lastattemptsummary").hide();
                        $(".showanalytics").find("#tabs-1").find("p.attemptsummarydes").hide();
                        $(".showanalytics").find("#tabs-1").find("p.attemptsummarydes").show();
                        if (totalData.userAttempts > 1) {
                            $(".showanalytics").find(".parentTabs").find("span.lastattemptsummary").show();
                            $(".showanalytics").find("#tabs-1").find("p.attemptsummarydes").show();
                            $(".showanalytics").find("#tabs-1").find("p.attemptsummarydes").hide();
                        }
                        setTimeout(function () {
                            $(".showanalytics").find("ul.nav-tabs a").click(function () {
                                $(this).tab('show');
                                // Center scroll on mobile.
                                if ($(window).width() < 480) {
                                    var outerContent = $('.mobile-overflow');
                                    var innerContent = $('.canvas-wrap');
                                    if (outerContent.length > 0) {
                                        outerContent.scrollLeft((innerContent.width() - outerContent.width()) / 2);
                                    }
                                }
                            });
                        }, 100);
                        $(".showanalytics").css("display", "block");
                        if (totalData.quizAttempt != 1) {
                            $("#tabs-2").find("ul").find("li").find("span.subtab1").show();
                            $("#tabs-2").find("ul").find("li").find("span.subtab2").hide();
                            $("#subtab21").find(".subtabmix").show();
                            $("#subtab21").find(".subtabtimechart").hide();
                        } else {
                            $("#tabs-2").find("ul").find("li").find("span.subtab1").hide();
                            $("#tabs-2").find("ul").find("li").find("span.subtab2").show();
                            $("#subtab21").find(".subtabmix").hide();
                            $("#subtab21").find(".subtabtimechart").show();
                        }
                        if (attemptsSnapshotArray.length > 0) {
                            $.each(attemptsSnapshotArray, function (i, v) {
                                v.destroy();
                            });
                        }
                        $('.attemptssnapshot').html('');
                        $.each(totalData.attemptssnapshot.data, function (key, value) {
                            var option = {
                                tooltips: {
                                    callbacks: {
                                        // use label callback to return the desired label
                                        label: function (tooltipItem, data) {
                                            return " " + data.labels[tooltipItem.index] + " : " + data.datasets[0].data[tooltipItem.index];
                                        }
                                    }
                                },
                            };
                            var Options = $.extend(totalData.attemptssnapshot.opt[key], option);
                            $('.attemptssnapshot').append('<div class="span6"><label><canvas id="attemptssnapshot' + key + '"></canvas><div id="js-legend' + key + '" class="chart-legend"></div></label><div class="download"><a class="download-canvas" data-canvas_id="attemptssnapshot' + key + '"></a></div></div>');
                            var chartConvention = document.getElementById("attemptssnapshot" + key).getContext('2d');
                            var attemptsSnapshot = new Chart(chartConvention, {
                                type: 'doughnut',
                                data: totalData.attemptssnapshot.data[key],
                                options: Options,
                            });
                            document.getElementById('js-legend' + key).innerHTML = attemptsSnapshot.generateLegend();
                            $('#js-legend' + key).find('ul').find('li').on("click", function (snaplegende) {
                                var index = $(this).index();
                                $(this).toggleClass("strike");
                                function first(p) {
                                    for (var i in p) { return p[i] };
                                }
                                var currentTab = first(attemptssSnapshot.config.data.datasets[0]._meta).data[index];
                                currentTab.hidden = !currentTab.hidden
                                attemptssSnapshot.update();
                            });
                            attemptsSnapshotArray.push(attemptsSnapshot);
                        });
                        var chartConvention = document.getElementById("questionpercat").getContext('2d');
                        if (questionPerCategories !== undefined) {
                            questionPerCategories.destroy();
                        }
                        var option = {
                            tooltips: {
                                callbacks: {
                                    // use label callback to return the desired label
                                    label: function (tooltipItem, data) {
                                        return " " + data.labels[tooltipItem.index] + " : " + data.datasets[0].data[tooltipItem.index];
                                    }
                                }
                            },
                        };
                        var Options = $.extend(totalData.questionPerCategories.opt, option);
                        questionPerCategories = new Chart(chartConvention, {
                            type: 'pie',
                            data: totalData.questionPerCategories.data,
                            options: Options,
                        });
                        document.getElementById('js-legendqpc').innerHTML = questionPerCategories.generateLegend();
                        $("#js-legendqpc > ul > li").on("click", function (legende) {
                            var index = $(this).index();
                            $(this).toggleClass("strike");
                            function first(p) {
                                for (var i in p) { return p[i] };
                            }
                            var currentTab = first(questionPerCategories.config.data.datasets[0]._meta).data[index];
                            currentTab.hidden = !currentTab.hidden
                            questionPerCategories.update();
                        });
                        var option = {
                            tooltips: {
                                custom: function (tooltip) {
                                    if (!tooltip) return;
                                    // disable displaying the color box;
                                    tooltip.displayColors = false;
                                }
                            },
                            scales: { xAxes: [{ scaleLabel: { display: true, labelString: 'Hardest Categories' } }], yAxes: [{ scaleLabel: { display: true, labelString: 'Hardness in percentage (%)' }, ticks: { beginAtZero: true, max: 100, callback: function (value) { if (Number.isInteger(value)) { return value; } } } }] }
                        };
                        var Options = $.extend(totalData.allUsers.opt, option);
                        var chartConvention = document.getElementById("allusers").getContext('2d');
                        if (allUsers !== undefined) {
                            allUsers.destroy();
                        }
                        allUsers = new Chart(chartConvention, {
                            type: 'bar',
                            data: totalData.allUsers.data,
                            options: Options
                        });
                        var option = {
                            tooltips: {
                                custom: function (tooltip) {
                                    if (!tooltip) return;
                                    // disable displaying the color box;
                                    tooltip.displayColors = false;
                                }
                            },
                            scales: { xAxes: [{ scaleLabel: { display: true, labelString: 'Hardest Categories' } }], yAxes: [{ scaleLabel: { display: true, labelString: 'Hardness in percentage (%)' }, ticks: { beginAtZero: true, max: 100, callback: function (value) { if (Number.isInteger(value)) { return value; } } } }] }
                        };
                        var Options = $.extend(totalData.loggedInUser.opt, option);
                        var chartConvention = document.getElementById("loggedinuser").getContext('2d');
                        if (loggedInUser !== undefined) {
                            loggedInUser.destroy();
                        }
                        loggedInUser = new Chart(chartConvention, {
                            type: 'bar',
                            data: totalData.loggedInUser.data,
                            options: Options
                        });
                        if (totalData.lastAttemptSummary.data != 0 && totalData.lastAttemptSummary.opt != 0) {
                            $(".showanalytics").find(".unattempted").hide();
                            $(".showanalytics").find("#lastattemptsummary").show();
                            var chartConvention = document.getElementById("lastattemptsummary");
                            chartConvention.height = 100;
                            var chartConvention1 = chartConvention.getContext('2d');
                            if (lastAttemptSummary !== undefined) {
                                lastAttemptSummary.destroy();
                            }
                            var option = {
                                tooltips: {
                                    custom: function (tooltip) {
                                        if (!tooltip) return;
                                        // disable displaying the color box;
                                        tooltip.displayColors = false;
                                    },
                                    callbacks: {
                                        // use label callback to return the desired label
                                        label: function (tooltipItem, data) {
                                            return tooltipItem.yLabel + " : " + tooltipItem.xLabel;
                                        },
                                        // remove title
                                        title: function (tooltipItem, data) {
                                            return;
                                        }
                                    }
                                }
                            };
                            var Options = $.extend(totalData.lastAttemptSummary.opt, option);
                            lastAttemptSummary = new Chart(chartConvention1, {
                                type: 'horizontalBar',
                                data: totalData.lastAttemptSummary.data,
                                options: Options
                            });
                        } else {
                            $(".showanalytics").find("#lastattemptsummary").hide();
                            $(".showanalytics").find("#lastattemptsummary").parent().append('<p class="unattempted"><b>Please attempt at least one question.</b></p>');
                        }
                        var option = {
                            tooltips: {
                                custom: function (tooltip) {
                                    if (!tooltip) return;
                                    // disable displaying the color box;
                                    tooltip.displayColors = false;
                                },
                                callbacks: {
                                    // use label callback to return the desired label
                                    label: function (tooltipItem, data) {
                                        return data.datasets[tooltipItem.datasetIndex].label + " : " + tooltipItem.yLabel;
                                    },
                                    // remove title
                                    title: function (tooltipItem, data) {
                                        return;
                                    }
                                }
                            },
                            scales: { xAxes: [{ scaleLabel: { display: true, labelString: 'Number of Attempts' } }], yAxes: [{ scaleLabel: { display: true, labelString: 'Cut Off Score' }, ticks: { beginAtZero: true, callback: function (value) { if (Number.isInteger(value)) { return value; } } } }] }
                        };
                        var Options = $.extend(totalData.mixChart.opt, option);
                        var chartConvention = document.getElementById("mixchart").getContext('2d');
                        if (mixChart !== undefined) {
                            mixChart.destroy();
                        }
                        mixChart = new Chart(chartConvention, {
                            type: 'line',
                            data: totalData.mixChart.data,
                            options: Options
                        });
                        var option = {
                            tooltips: {
                                custom: function (tooltip) {
                                    if (!tooltip) return;
                                    // disable displaying the color box;
                                    tooltip.displayColors = false;
                                },
                                callbacks: {
                                    // use label callback to return the desired label
                                    label: function (tooltipItem, data) {
                                        return tooltipItem.yLabel + " : " + tooltipItem.xLabel;
                                    },
                                    // remove title
                                    title: function (tooltipItem, data) {
                                        return;
                                    }
                                }
                            },
                            scales: { xAxes: [{ scaleLabel: { display: true, labelString: 'Score' }, ticks: { beginAtZero: true, callback: function (value) { if (Number.isInteger(value)) { return value; } } } }] }
                        };
                        var Options = $.extend(totalData.timeChart.opt, option);
                        var chartConvention = document.getElementById("timechart").getContext('2d');
                        if (timeChart !== undefined) {
                            timeChart.destroy();
                        }
                        timeChart = new Chart(chartConvention, {
                            type: 'horizontalBar',
                            data: totalData.timeChart.data,
                            options: Options
                        });
                        var chartConvention = document.getElementById("gradeanalysis").getContext('2d');
                        if (gradeAnalysis !== undefined) {
                            gradeAnalysis.destroy();
                        }
                        var option = {
                            tooltips: {
                                custom: function (tooltip) {
                                    if (!tooltip) return;
                                    // disable displaying the color box;
                                    tooltip.displayColors = false;
                                },
                                callbacks: {
                                    // use label callback to return the desired label
                                    label: function (tooltipItem, data) {
                                        return "Percentage Scored (" + data.labels[tooltipItem.index] + ") : " + data.datasets[0].data[tooltipItem.index];
                                    }
                                }
                            }
                        };
                        var Options = $.extend(totalData.gradeAnalysis.opt, option);
                        gradeAnalysis = new Chart(chartConvention, {
                            type: 'pie',
                            data: totalData.gradeAnalysis.data,
                            options: Options
                        });
                        document.getElementById('js-legendgrade').innerHTML = gradeAnalysis.generateLegend();
                        $("#js-legendgrade > ul > li").on("click", function (legendgrade) {
                            var index = $(this).index();
                            $(this).toggleClass("strike");
                            function first(p) {
                                for (var i in p) { return p[i] };
                            }
                            var currentTab = first(gradeAnalysis.config.data.datasets[0]._meta).data[index];
                            currentTab.hidden = !currentTab.hidden
                            gradeAnalysis.update();
                        });
                        var chartConvention = document.getElementById("quesanalysis").getContext('2d');
                        if (quesAnalysis !== undefined) {
                            quesAnalysis.destroy();
                        }
                        var option = {
                            tooltips: {
                                custom: function (tooltip) {
                                    if (!tooltip) return;
                                    // disable displaying the color box;
                                    tooltip.displayColors = false;
                                },
                                callbacks: {
                                    // use label callback to return the desired label
                                    label: function (tooltipItem, data) {
                                        return [data.datasets[tooltipItem.datasetIndex].label + " : " + tooltipItem.yLabel, "(Click to Review Question & Last Attempt)"];
                                         
                                    }
                                }
                            },
                            scales: { xAxes: [{ scaleLabel: { display: true, labelString: 'Question Number' } }], yAxes: [{ scaleLabel: { display: true, labelString: 'Number of Attempts' }, ticks: { beginAtZero: true, callback: function (value) { if (Number.isInteger(value)) { return value; } } } }] }
                        };
                        var Options = $.extend(totalData.quesAnalysis.opt, option);

                        quesAnalysis = new Chart(chartConvention, {
                            type: 'line',
                            data: totalData.quesAnalysis.data,
                            options: Options
                        });
                        var option = {
                            tooltips: {
                                custom: function (tooltip) {
                                    if (!tooltip) return;
                                    // disable displaying the color box;
                                    tooltip.displayColors = false;
                                },
                                callbacks: {
                                    // use label callback to return the desired label
                                    label: function (tooltipItem, data) {
                                        return [data.datasets[tooltipItem.datasetIndex].label + " : " + tooltipItem.yLabel, "(Click to Review Question & Last Attempt)"];
                                        
                                    },
                                    // remove title
                                    title: function (tooltipItem, data) {
                                        return;
                                    }
                                }
                            },
                            scales: { xAxes: [{ scaleLabel: { display: true, labelString: 'Hardest Questions' } }], yAxes: [{ scaleLabel: { display: true, labelString: 'Number of Attempts' }, ticks: { beginAtZero: true, callback: function (value) { if (Number.isInteger(value)) { return value; } } } }] }
                        };
                        var Options = $.extend(totalData.hardestQuestions.opt, option);
                        var chartConvention = document.getElementById("hardest-questions").getContext('2d');
                        if (hardestQuestions !== undefined) {
                            hardestQuestions.destroy();
                        }
                        hardestQuestions = new Chart(chartConvention, {
                            type: 'bar',
                            data: totalData.hardestQuestions.data,
                            options: Options
                        });
                    }
                })
                var canvasQuestionAnalysis = document.getElementById("quesanalysis");
                if (canvasQuestionAnalysis) {
                    canvasQuestionAnalysis.onclick = function (questionevent) {
                        var activePoints = quesAnalysis.getElementsAtEvent(questionevent);
                        var chartData = activePoints[0]['_chart'].config.data;
                        var idx = activePoints[0]['_index'];
                        var label = chartData.labels[idx];
                        if (allQuestions !== undefined) {
                            var quesPage = 0;
                            $.each(allQuestions, function (i, quesid) {
                                if (label == quesid.split(",")[0]) {
                                    var quesid = quesid.split(",")[1];
                                    var id = quizid;
                                    if (quesPage == 0) {
                                        var newwindow = window.open(rooturl + '/mod/quiz/review.php?attempt=' + lastUserQuizAttemptID + '&showall=' + 0, '', 'height=500,width=800');
                                    } else {
                                        var newwindow = window.open(rooturl + '/mod/quiz/review.php?attempt=' + lastUserQuizAttemptID + '&page=' + quesPage, '', 'height=500,width=800');
                                    }
                                    if (window.focus) {
                                        newwindow.focus();
                                    }
                                    return false;
                                }
                                quesPage++;
                            });
                        }
                    };
                }
                var canvasHardestQuestions = document.getElementById("hardest-questions");
                if (canvasHardestQuestions) {
                    canvasHardestQuestions.onclick = function (attemptevent) {
                        var activePoints = hardestQuestions.getElementsAtEvent(attemptevent);
                        var chartData = activePoints[0]['_chart'].config.data;
                        var idx = activePoints[0]['_index'];
                        var label = chartData.labels[idx];
                        if (allQuestions !== undefined) {
                            var quesPage = 0;
                            $.each(allQuestions, function (i, quesid) {
                                if (label == quesid.split(",")[0]) {
                                    var quesid = quesid.split(",")[1];
                                    var id = quizid;
                                    if (quesPage == 0) {
                                        var newwindow = window.open(rooturl + '/mod/quiz/review.php?attempt=' + lastUserQuizAttemptID + '&showall=' + 0, '','height=500,width=800');
                                    } else {
                                        var newwindow = window.open(rooturl + '/mod/quiz/review.php?attempt=' + lastUserQuizAttemptID + '&page=' + quesPage,'', 'height=500,width=800');
                                    }
                                    if (window.focus) {
                                        newwindow.focus();
                                    }
                                    return false;
                                }
                                quesPage++;
                            });
                        }
                    };
                }

            });
            $("#viewanalytic").one("click", function () {
                $(".showanalytics").find("canvas").each(function () {
                    var canvasid = $(this).attr("id");
                    $(this).parent().append('<div class="download"><a class="download-canvas" data-canvas_id="' + canvasid + '"></a></div>');
                });
            });
            $('body').on('click', '.download-canvas', function () {
                var canvasId = $(this).data('canvas_id');
                downloadCanvas(this, canvasId, canvasId + '.jpeg');
            });
            function downloadCanvas(link, canvasId, filename) {
                link.href = document.getElementById(canvasId).toDataURL("image/jpeg");
                link.download = filename;
            }
        }
    };
});
