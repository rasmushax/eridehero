document.addEventListener('DOMContentLoaded', function() {
    if (typeof priceChartData !== 'undefined' && priceChartData.labels.length > 0) {
        const ctx = document.getElementById('priceChart');
        if (ctx) {

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: priceChartData.labels,
                    datasets: [{
                        label: 'Price History',
                        data: priceChartData.datasets[0].data,
                        borderColor: 'rgb(94, 44, 237)',
                        backgroundColor: 'rgba(111, 97, 242, 0.1)',
                        fill: true,
                        tension: 0,
                        pointRadius: 0,
                    }]
                },
                options: {
					
                    responsive: true,
					maintainAspectRatio: true,
                    aspectRatio: 3,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
					font: {
							family: 'Poppins'
						},
                    plugins: {
                        legend: {
                            display: false
                        },
						tooltip: {
								callbacks: {
									title: function(context) {
										const price = context[0].parsed.y;
										return formattedPrice = new Intl.NumberFormat('en-US', { 
												style: 'currency', 
												currency: 'USD',
												minimumFractionDigits: 2,
												maximumFractionDigits: 2
												}).format(price)+" USD";
									},
									label: function(context) {
										const rawDate = context.raw.x;
										let formattedDate = 'Invalid Date';

										if (rawDate && typeof rawDate === 'string') {
											const [year, month, day] = rawDate.split('-').map(Number);
											if (!isNaN(year) && !isNaN(month) && !isNaN(day)) {
												const date = new Date(Date.UTC(year, month - 1, day));
												formattedDate = date.toLocaleDateString('en-US', { 
													year: 'numeric', 
													month: 'long', 
													day: 'numeric',
													timeZone: 'UTC'
												});
											}
										}
										return formattedDate;
									},
									afterLabel: function(context){
										return context.raw.domain;
									}
								
								},
								displayColors: false,
								padding:8,
								bodySpacing:5,
								titleFont: {
									size:16,
									family: "Poppins"
								},
								titleColor: "white",
								titleAlign: "center",
								bodyAlign: "center",
								bodyFont: {
									size:14,
									family: "Poppins"
								},
								bodyColor: "#f9fafe",
								backgroundColor:"#21273a",
								
								
						}
                    },
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                unit: 'day'
                            },
                            grid: {
                                display: false,
								drawBorder: false,
                            },
                            ticks: {
                                maxTicksLimit: 10, // Adjust this value to show more or fewer ticks
                                font: {
                                    size: 12,
									weight: 'normal'
                                },
                                color: '#636d93'
                            },
                            border: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: false,
							offset: false,
							position:'left',
                            border: {
                                display: false
                            },
                            ticks: {
								
                                callback: function(value, index, values) {
                                    return '$' + value.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0}) + ' USD';
                                },
                                font: {
                                    size: 12,
                                    weight: 'normal'
                                },
                                color: '#636d93'
                            },
							
                            grid: {
                                color: '#e3e8ed'
                            }
                        }
                    },
					layout: {
					  padding: {
						left: 0,
						right: 0, // Adjust as needed
					  }
					}
                }
            });
        }
    }
});