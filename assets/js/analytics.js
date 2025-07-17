/**
 * Analytics JS for Resubscribe Controls for WCSubs
 */
(function ($) {
  "use strict";

  // Set translations globally
  window.rcwcs_analytics_i18n = window.rcwcs_analytics_i18n || {
    refresh: "Refresh Data",
    error: "Error loading data. Please try again.",
    price_differences: "Price Differences",
    price_difference: "Price Difference",
    date: "Date",
    resubscriptions_by_product: "Resubscriptions by Product",
  };

  $(document).ready(function () {
    // Initialize analytics functionality
    rcwcsAnalytics.init();
  });

  // Analytics object
  var rcwcsAnalytics = {
    chart: null,
    pieChart: null,

    init: function () {
      // Initialize the charts directly (Chart.js is now loaded via wp_enqueue_script)
      this.initCharts();
      this.setupRefresh();
      this.setupExport();
      this.setupDateFilter();
    },

    initCharts: function () {
      // Initialize charts if containers exist and Chart.js is available
      if (typeof Chart !== "undefined") {
        if ($("#rcwcs-price-chart").length) {
          this.createPriceChart();
        }

        if ($("#rcwcs-product-chart").length) {
          this.createProductChart();
        }

        // Load initial data
        this.loadChartData();
      } else {
        console.error("Chart.js is not loaded.");
      }
    },

    createPriceChart: function () {
      var ctx = document.getElementById("rcwcs-price-chart").getContext("2d");

      this.chart = new Chart(ctx, {
        type: "line",
        data: {
          labels: [],
          datasets: [
            {
              label: rcwcs_analytics_i18n.price_differences,
              data: [],
              backgroundColor: "rgba(54, 162, 235, 0.2)",
              borderColor: "rgba(54, 162, 235, 1)",
              borderWidth: 1,
              tension: 0.1,
            },
          ],
        },
        options: {
          responsive: true,
          scales: {
            y: {
              beginAtZero: false,
              title: {
                display: true,
                text: rcwcs_analytics_i18n.price_difference,
              },
            },
            x: {
              title: {
                display: true,
                text: rcwcs_analytics_i18n.date,
              },
            },
          },
        },
      });
    },

    createProductChart: function () {
      var ctx = document.getElementById("rcwcs-product-chart").getContext("2d");

      this.pieChart = new Chart(ctx, {
        type: "pie",
        data: {
          labels: [],
          datasets: [
            {
              data: [],
              backgroundColor: [
                "rgba(255, 99, 132, 0.5)",
                "rgba(54, 162, 235, 0.5)",
                "rgba(255, 206, 86, 0.5)",
                "rgba(75, 192, 192, 0.5)",
                "rgba(153, 102, 255, 0.5)",
                "rgba(255, 159, 64, 0.5)",
              ],
              borderColor: [
                "rgba(255, 99, 132, 1)",
                "rgba(54, 162, 235, 1)",
                "rgba(255, 206, 86, 1)",
                "rgba(75, 192, 192, 1)",
                "rgba(153, 102, 255, 1)",
                "rgba(255, 159, 64, 1)",
              ],
              borderWidth: 1,
            },
          ],
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              position: "right",
            },
            title: {
              display: true,
              text: rcwcs_analytics_i18n.resubscriptions_by_product,
            },
          },
        },
      });
    },

    loadChartData: function () {
      $.ajax({
        url: rcwcs_analytics.ajax_url,
        type: "POST",
        data: {
          action: "rcwcs_get_chart_data",
          nonce: rcwcs_analytics.nonce,
          start_date: $("#rcwcs-date-start").val(),
          end_date: $("#rcwcs-date-end").val(),
        },
        success: function (response) {
          if (response.success && response.data) {
            rcwcsAnalytics.updateCharts(response.data);
          }
        },
      });
    },

    updateCharts: function (data) {
      // Update line chart
      if (this.chart && data.price_history) {
        this.chart.data.labels = data.price_history.dates;
        this.chart.data.datasets[0].data = data.price_history.differences;
        this.chart.update();
      }

      // Update pie chart
      if (this.pieChart && data.products) {
        this.pieChart.data.labels = data.products.labels;
        this.pieChart.data.datasets[0].data = data.products.data;
        this.pieChart.update();
      }
    },

    setupRefresh: function () {
      // Add refresh button
      var $refreshButton = $(
        '<button type="button" class="button rcwcs-refresh-analytics" style="margin-left: 10px;">'
      ).text(rcwcs_analytics_i18n.refresh);
      $(".rcwcs-analytics-dashboard").before($refreshButton);

      // Attach refresh handler
      $refreshButton.on("click", function () {
        rcwcsAnalytics.refreshData();
      });
    },

    setupDateFilter: function () {
      // Add date filters if they exist
      if ($("#rcwcs-date-filter").length) {
        $("#rcwcs-date-filter-submit").on("click", function (e) {
          e.preventDefault();
          rcwcsAnalytics.loadChartData();
          rcwcsAnalytics.refreshData();
        });
      }
    },

    refreshData: function () {
      // Show loading state
      $(".rcwcs-analytics-dashboard").css("opacity", "0.5");

      // Make AJAX request
      $.ajax({
        url: rcwcs_analytics.ajax_url,
        type: "POST",
        data: {
          action: "rcwcs_get_analytics",
          nonce: rcwcs_analytics.nonce,
          start_date: $("#rcwcs-date-start").val(),
          end_date: $("#rcwcs-date-end").val(),
        },
        success: function (response) {
          if (response.success && response.data) {
            rcwcsAnalytics.updateDisplay(response.data);
          } else {
            alert(rcwcs_analytics_i18n.error);
          }
        },
        error: function () {
          alert(rcwcs_analytics_i18n.error);
        },
        complete: function () {
          $(".rcwcs-analytics-dashboard").css("opacity", "1");
        },
      });
    },

    updateDisplay: function (data) {
      // Update summary cards
      $(".rcwcs-analytics-card:eq(0) .rcwcs-analytics-count").text(
        data.total_resubscriptions
      );
      $(".rcwcs-analytics-card:eq(1) .rcwcs-analytics-count").text(
        data.total_price_updates
      );

      // Format price with WooCommerce currency formatting
      $(".rcwcs-analytics-card:eq(2) .rcwcs-analytics-count").text(
        this.formatPrice(data.avg_price_increase)
      );

      // Refresh the table rows if they exist
      if (data.recent_resubscriptions && data.recent_resubscriptions.length) {
        this.refreshTableRows(data.recent_resubscriptions);
      }
    },

    refreshTableRows: function (resubscriptions) {
      var $tableBody = $(".rcwcs-analytics-tables table tbody");
      if (!$tableBody.length) return;

      // Clear current rows
      $tableBody.empty();

      // Add new rows
      resubscriptions.forEach(function (item) {
        var diffClass =
          parseFloat(item.price_difference) >= 0
            ? "price-increase"
            : "price-decrease";

        var row =
          "<tr>" +
          "<td>" +
          item.resubscribe_date +
          "</td>" +
          '<td><a href="' +
          rcwcs_analytics.admin_url +
          "post.php?post=" +
          item.subscription_id +
          '&action=edit">#' +
          item.subscription_id +
          "</a></td>" +
          "<td>" +
          item.customer_name +
          "</td>" +
          "<td>" +
          item.product_name +
          "</td>" +
          "<td>" +
          rcwcsAnalytics.formatPrice(item.original_price) +
          "</td>" +
          "<td>" +
          rcwcsAnalytics.formatPrice(item.new_price) +
          "</td>" +
          '<td><span class="' +
          diffClass +
          '">' +
          rcwcsAnalytics.formatPrice(item.price_difference) +
          "</span></td>" +
          "</tr>";

        $tableBody.append(row);
      });
    },

    setupExport: function () {
      // Handle export form submission
      $(".rcwcs-analytics-export form").on("submit", function (e) {
        // Add date range to export if set
        if ($("#rcwcs-date-start").length && $("#rcwcs-date-end").length) {
          var startDate = $("#rcwcs-date-start").val();
          var endDate = $("#rcwcs-date-end").val();

          if (startDate && endDate) {
            $(this).append(
              '<input type="hidden" name="start_date" value="' +
                startDate +
                '">'
            );
            $(this).append(
              '<input type="hidden" name="end_date" value="' + endDate + '">'
            );
          }
        }
      });
    },

    // Simple price formatter
    formatPrice: function (price) {
      return rcwcs_analytics.currency_symbol + parseFloat(price).toFixed(2);
    },
  };
})(jQuery);
