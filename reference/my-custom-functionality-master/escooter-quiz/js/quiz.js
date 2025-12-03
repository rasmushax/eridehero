/**
 * ERideHero Electric Scooter Quiz
 * Interactive quiz to help users find their perfect electric scooter
 * Vanilla JavaScript implementation
 */

(function() {
    'use strict';

    // Quiz configuration and data
    const quizConfig = {
        // Quiz questions
        questions: [
            {
                id: 'primary_use',
                question: 'What will be your primary use for the scooter?',
                options: [
                    {
                        id: 'commuting',
                        title: 'Daily Commuting',
                        description: 'To work or school, on a regular basis',
                        image: 'commuting-icon.svg'
                    },
                    {
                        id: 'recreational',
                        title: 'Recreational Riding',
                        description: 'Weekend fun, casual rides',
                        image: 'recreational-icon.svg'
                    },
                    {
                        id: 'mixed',
                        title: 'Mixed Use',
                        description: 'Both commuting and recreation',
                        image: 'mixed-icon.svg'
                    },
                    {
                        id: 'offroad',
                        title: 'Off-Road Adventure',
                        description: 'Trails and rough terrain',
                        image: 'offroad-icon.svg'
                    }
                ]
            },
            {
                id: 'budget',
                question: 'What\'s your budget range?',
                options: [
                    {
                        id: 'budget-entry',
                        title: 'Entry-level',
                        description: '$399 - $699',
                        image: 'entry-budget-icon.svg'
                    },
                    {
                        id: 'budget-mid',
                        title: 'Mid-range',
                        description: '$700 - $1,299',
                        image: 'mid-budget-icon.svg'
                    },
                    {
                        id: 'budget-high',
                        title: 'High-end',
                        description: '$1,300 - $2,999',
                        image: 'high-budget-icon.svg'
                    },
                    {
                        id: 'budget-premium',
                        title: 'Premium / Hyperscooter',
                        description: '$3,000+',
                        image: 'premium-budget-icon.svg'
                    }
                ]
            },
            {
                id: 'rider_weight',
                question: 'What\'s your weight?',
                options: [
                    {
                        id: 'weight-light',
                        title: 'Under 175 lbs',
                        description: 'Most scooters can handle this weight easily',
                        image: 'weight-light-icon.svg'
                    },
                    {
                        id: 'weight-medium',
                        title: '175 - 220 lbs',
                        description: 'Mid-range weight capacity needed',
                        image: 'weight-medium-icon.svg'
                    },
                    {
                        id: 'weight-heavy',
                        title: '220 - 265 lbs',
                        description: 'Higher capacity scooters recommended',
                        image: 'weight-heavy-icon.svg'
                    },
                    {
                        id: 'weight-very-heavy',
                        title: '265+ lbs',
                        description: 'Specialized high-capacity scooters needed',
                        image: 'weight-very-heavy-icon.svg'
                    }
                ]
            },
            {
                id: 'portability',
                question: 'How important is portability to you?',
                options: [
                    {
                        id: 'portability-very',
                        title: 'Extremely Important',
                        description: 'Need to carry it frequently/long distances',
                        image: 'portability-very-icon.svg'
                    },
                    {
                        id: 'portability-somewhat',
                        title: 'Somewhat Important',
                        description: 'Occasional carrying/short distances',
                        image: 'portability-somewhat-icon.svg'
                    },
                    {
                        id: 'portability-not',
                        title: 'Not Important',
                        description: 'Rarely/never need to carry it',
                        image: 'portability-not-icon.svg'
                    }
                ]
            },
            {
                id: 'commute_distance',
                question: 'What\'s your typical one-way commute distance?',
                options: [
                    {
                        id: 'distance-short',
                        title: 'Short',
                        description: 'Under 5 miles each way',
                        image: 'distance-short-icon.svg'
                    },
                    {
                        id: 'distance-medium',
                        title: 'Medium',
                        description: '5 - 10 miles each way',
                        image: 'distance-medium-icon.svg'
                    },
                    {
                        id: 'distance-long',
                        title: 'Long',
                        description: 'Over 10 miles each way',
                        image: 'distance-long-icon.svg'
                    }
                ],
                conditional: {
                    questionId: 'primary_use',
                    showWhen: ['commuting', 'mixed']
                }
            },
            {
                id: 'terrain',
                question: 'What type of terrain will you primarily ride on?',
                options: [
                    {
                        id: 'terrain-smooth',
                        title: 'Mostly Smooth Surfaces',
                        description: 'Well-maintained roads and paths',
                        image: 'terrain-smooth-icon.svg'
                    },
                    {
                        id: 'terrain-mixed',
                        title: 'Mixed Terrain',
                        description: 'Some rough patches and bumps',
                        image: 'terrain-mixed-icon.svg'
                    },
                    {
                        id: 'terrain-rough',
                        title: 'Frequently Rough Terrain',
                        description: 'Many bumps, cracks, and poor surfaces',
                        image: 'terrain-rough-icon.svg'
                    },
                    {
                        id: 'terrain-offroad',
                        title: 'Off-Road Trails',
                        description: 'Dirt, gravel, and off-road paths',
                        image: 'terrain-offroad-icon.svg'
                    }
                ]
            },
            {
                id: 'priority_features',
                question: 'Which features are most important to you? (Choose up to 2)',
                options: [
                    {
                        id: 'range',
                        title: 'Long Range',
                        description: 'Maximum distance per charge',
                        image: 'priority-range-icon.svg'
                    },
                    {
                        id: 'speed',
                        title: 'High Speed',
                        description: 'Faster top speed and acceleration',
                        image: 'priority-speed-icon.svg'
                    },
                    {
                        id: 'comfort',
                        title: 'Ride Comfort',
                        description: 'Smoother ride on various surfaces',
                        image: 'priority-comfort-icon.svg'
                    },
                    {
                        id: 'weather',
                        title: 'Weather Resistance',
                        description: 'Better handling of wet conditions',
                        image: 'priority-weather-icon.svg'
                    },
                    {
                        id: 'build',
                        title: 'Durability/Build Quality',
                        description: 'Longer lasting, more reliable',
                        image: 'priority-build-icon.svg'
                    },
                    {
                        id: 'portability',
                        title: 'Portability',
                        description: 'Lighter weight, easier to carry',
                        image: 'priority-portability-icon.svg'
                    }
                ],
                multiSelect: true,
                maxSelections: 2
            }
        ]
    };

    // Quiz state
    let quizState = {
        currentQuestion: 0,
        totalQuestions: 0,
        answers: {},
        results: null
    };

    /**
     * Initialize the quiz when document is ready
     */
    document.addEventListener('DOMContentLoaded', function() {
        initQuiz();
        setupEventListeners();
    });

    /**
     * Initialize the quiz
     */
    function initQuiz() {
        // Filter questions based on conditionals
        quizState.totalQuestions = quizConfig.questions.length;
    }

    /**
     * Set up event listeners
     */
    function setupEventListeners() {
        // Start button
        const startButton = document.getElementById('eridehero-quiz-start');
        if (startButton) {
            startButton.addEventListener('click', function() {
                // Hide intro, show questions
                document.getElementById('eridehero-quiz-intro').style.display = 'none';
                document.getElementById('eridehero-quiz-questions-container').style.display = 'block';
                
                // Fade-in animation
                fadeIn(document.getElementById('eridehero-quiz-questions-container'));
                
                // Show first question
                showQuestion(0);
            });
        }
        
        // Next button
        const nextButton = document.getElementById('eridehero-quiz-next');
        if (nextButton) {
            nextButton.addEventListener('click', function() {
                // Animate question transition
                const questionsContainer = document.getElementById('eridehero-quiz-questions');
                
                // Fade out current question
                fadeOut(questionsContainer, function() {
                    // Filter questions based on current answers
                    const filteredQuestions = getFilteredQuestions();
                    
                    // Move to next question
                    quizState.currentQuestion++;
                    
                    if (quizState.currentQuestion < filteredQuestions.length) {
                        // Show next question
                        showQuestion(quizState.currentQuestion);
                        // Fade in next question
                        fadeIn(questionsContainer);
                    } else {
                        // Show results
                        submitQuiz();
                    }
                });
            });
        }
        
        // Back button
        const backButton = document.getElementById('eridehero-quiz-back');
        if (backButton) {
            backButton.addEventListener('click', function() {
                // Animate question transition
                const questionsContainer = document.getElementById('eridehero-quiz-questions');
                
                // Fade out current question
                fadeOut(questionsContainer, function() {
                    // Move to previous question
                    quizState.currentQuestion--;
                    
                    if (quizState.currentQuestion >= 0) {
                        // Show previous question
                        showQuestion(quizState.currentQuestion);
                        // Fade in previous question
                        fadeIn(questionsContainer);
                    } else {
                        // Show intro
                        document.getElementById('eridehero-quiz-questions-container').style.display = 'none';
                        document.getElementById('eridehero-quiz-intro').style.display = 'block';
                        
                        // Fade-in animation
                        fadeIn(document.getElementById('eridehero-quiz-intro'));
                        
                        // Reset current question
                        quizState.currentQuestion = 0;
                    }
                });
            });
        }
        
        // Retake quiz button
        const retakeButton = document.getElementById('eridehero-quiz-retake');
        if (retakeButton) {
            retakeButton.addEventListener('click', function() {
                // Reset quiz state
                quizState = {
                    currentQuestion: 0,
                    totalQuestions: quizConfig.questions.length,
                    answers: {},
                    results: null
                };
                
                // Hide results, show intro
                document.getElementById('eridehero-quiz-results').style.display = 'none';
                document.getElementById('eridehero-quiz-intro').style.display = 'block';
                
                // Fade-in animation
                fadeIn(document.getElementById('eridehero-quiz-intro'));
            });
        }
        
        // Email submission
        const emailForm = document.getElementById('eridehero-email-form');
        if (emailForm) {
            emailForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const emailInput = document.getElementById('eridehero-email-input');
                const email = emailInput ? emailInput.value : '';
                
                if (email && isValidEmail(email)) {
                    // Show loader
                    const emailLoader = document.getElementById('eridehero-email-loader');
                    if (emailLoader) {
                        emailLoader.style.display = 'flex';
                    }
                    
                    // Submit email using fetch API
                    fetch(ERideHeroQuiz.ajaxurl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'save_quiz_results',
                            nonce: ERideHeroQuiz.nonce,
                            email: email,
                            quiz_answers: JSON.stringify(quizState.answers),
                            recommendations: JSON.stringify(quizState.results)
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Hide loader
                        if (emailLoader) {
                            emailLoader.style.display = 'none';
                        }
                        
                        if (data.success) {
                            // Show success message
                            const successMessage = document.getElementById('eridehero-email-success');
                            if (successMessage) {
                                successMessage.style.display = 'block';
                                fadeIn(successMessage);
                            }
                            
                            // Hide form
                            if (emailForm) {
                                emailForm.style.display = 'none';
                            }
                        } else {
                            // Show error message
                            const errorMessage = document.getElementById('eridehero-email-error');
                            if (errorMessage) {
                                errorMessage.textContent = data.data.message;
                                errorMessage.style.display = 'block';
                                fadeIn(errorMessage);
                            }
                        }
                    })
                    .catch(error => {
                        // Hide loader
                        if (emailLoader) {
                            emailLoader.style.display = 'none';
                        }
                        
                        // Show error message
                        const errorMessage = document.getElementById('eridehero-email-error');
                        if (errorMessage) {
                            errorMessage.textContent = 'An error occurred. Please try again.';
                            errorMessage.style.display = 'block';
                            fadeIn(errorMessage);
                        }
                    });
                } else {
                    // Show error message
                    const errorMessage = document.getElementById('eridehero-email-error');
                    if (errorMessage) {
                        errorMessage.textContent = 'Please enter a valid email address.';
                        errorMessage.style.display = 'block';
                        fadeIn(errorMessage);
                    }
                }
            });
        }
        
        // Delegate for option clicks
        document.addEventListener('click', function(e) {
            // Check if click is on an option or its child
            let targetElement = e.target;
            
            // Traverse up to find the option element
            while (targetElement && !targetElement.classList.contains('eridehero-quiz-option')) {
                if (targetElement.parentElement) {
                    targetElement = targetElement.parentElement;
                } else {
                    return; // Not an option or child of option
                }
            }
            
            // If we found an option
            if (targetElement && targetElement.classList.contains('eridehero-quiz-option')) {
                const questionId = targetElement.getAttribute('data-question-id');
                const optionId = targetElement.getAttribute('data-option-id');
                const isMultiSelect = targetElement.classList.contains('multiselect');
                
                if (isMultiSelect) {
                    // For multi-select questions
                    if (targetElement.classList.contains('selected')) {
                        // Deselect option
                        targetElement.classList.remove('selected');
                        
                        // Add deselect animation
                        targetElement.classList.add('deselect-animation');
                        setTimeout(function() {
                            targetElement.classList.remove('deselect-animation');
                        }, 300);
                        
                        // Remove from answers
                        const index = quizState.answers[questionId].indexOf(optionId);
                        if (index !== -1) {
                            quizState.answers[questionId].splice(index, 1);
                        }
                    } else {
                        // Check if we've reached max selections
                        const currentQuestion = quizConfig.questions.find(q => q.id === questionId);
                        const maxSelections = currentQuestion.maxSelections || 1;
                        const currentSelections = quizState.answers[questionId] ? quizState.answers[questionId].length : 0;
                        
                        if (currentSelections < maxSelections) {
                            // Select option
                            targetElement.classList.add('selected');
                            
                            // Add select animation
                            targetElement.classList.add('select-animation');
                            setTimeout(function() {
                                targetElement.classList.remove('select-animation');
                            }, 300);
                            
                            // Add to answers
                            if (!quizState.answers[questionId]) {
                                quizState.answers[questionId] = [];
                            }
                            quizState.answers[questionId].push(optionId);
                        }
                    }
                } else {
                    // For single-select questions
                    // Deselect all options for this question
                    const options = document.querySelectorAll(`.eridehero-quiz-option[data-question-id="${questionId}"]`);
                    options.forEach(option => {
                        option.classList.remove('selected');
                    });
                    
                    // Select this option
                    targetElement.classList.add('selected');
                    
                    // Add select animation
                    targetElement.classList.add('select-animation');
                    setTimeout(function() {
                        targetElement.classList.remove('select-animation');
                    }, 300);
                    
                    // Update answers
                    quizState.answers[questionId] = optionId;
                }
                
                // Enable/disable next button based on if any option is selected
                toggleNextButton();
            }
        });
    }

    /**
     * Show a specific question
     * 
     * @param {number} index Question index
     */
    function showQuestion(index) {
        // Get filtered questions based on current answers
        const filteredQuestions = getFilteredQuestions();
        
        if (index >= filteredQuestions.length) {
            // No more questions, submit quiz
            submitQuiz();
            return;
        }
        
        const question = filteredQuestions[index];
        const questionContainer = document.getElementById('eridehero-quiz-questions');
        
        if (!questionContainer) {
            return;
        }
        
        // Update progress bar
        const progress = Math.round(((index + 1) / filteredQuestions.length) * 100);
        const progressBar = document.getElementById('eridehero-quiz-progress-bar');
        if (progressBar) {
            progressBar.style.width = `${progress}%`;
        }
        
        // Clear previous question
        questionContainer.innerHTML = '';
        
        // Build question HTML
        const questionElement = document.createElement('div');
        questionElement.className = 'eridehero-quiz-question';
        questionElement.textContent = question.question;
        
        const optionsContainer = document.createElement('div');
        optionsContainer.className = 'eridehero-quiz-options';
        
        // Add options
        question.options.forEach(option => {
            // Create option element
            const optionElement = document.createElement('div');
            optionElement.className = 'eridehero-quiz-option';
            optionElement.setAttribute('data-question-id', question.id);
            optionElement.setAttribute('data-option-id', option.id);
            
            // For multi-select questions
            if (question.multiSelect) {
                optionElement.classList.add('multiselect');
            }
            
            // Check if this option is selected
            if (question.multiSelect) {
                if (quizState.answers[question.id] && quizState.answers[question.id].includes(option.id)) {
                    optionElement.classList.add('selected');
                }
            } else {
                if (quizState.answers[question.id] === option.id) {
                    optionElement.classList.add('selected');
                }
            }
            
            // Option image
            const optionImage = document.createElement('img');
            optionImage.className = 'eridehero-quiz-option-image';
            const imgSrc = option.image.startsWith('http') ? option.image : `${ERideHeroQuiz.pluginurl}escooter-quiz/img/${option.image}`;
            optionImage.src = imgSrc;
            optionImage.alt = option.title;
            
            // Option content
            const optionContent = document.createElement('div');
            optionContent.className = 'eridehero-quiz-option-content';
            
            const optionTitle = document.createElement('div');
            optionTitle.className = 'eridehero-quiz-option-title';
            optionTitle.textContent = option.title;
            
            const optionDesc = document.createElement('div');
            optionDesc.className = 'eridehero-quiz-option-desc';
            optionDesc.textContent = option.description;
            
            optionContent.appendChild(optionTitle);
            optionContent.appendChild(optionDesc);
            optionElement.appendChild(optionImage);
            optionElement.appendChild(optionContent);
            optionsContainer.appendChild(optionElement);
        });
        
        // Append question and options to container
        questionContainer.appendChild(questionElement);
        questionContainer.appendChild(optionsContainer);
        
        // Update navigation buttons
        const backButton = document.getElementById('eridehero-quiz-back');
        if (backButton) {
            backButton.disabled = index === 0;
        }
        
        // Enable/disable next button based on if any option is selected
        toggleNextButton();
        
        // Show/hide "Submit" text on next button
        const nextButton = document.getElementById('eridehero-quiz-next');
        if (nextButton) {
            if (index === filteredQuestions.length - 1) {
                nextButton.textContent = 'See Results';
                nextButton.classList.add('submit-button');
            } else {
                nextButton.textContent = 'Next';
                nextButton.classList.remove('submit-button');
            }
        }
    }

    /**
     * Toggle the next button based on selection state
     */
    function toggleNextButton() {
        const filteredQuestions = getFilteredQuestions();
        const currentQuestion = filteredQuestions[quizState.currentQuestion];
        const nextButton = document.getElementById('eridehero-quiz-next');
        
        if (!nextButton || !currentQuestion) {
            return;
        }
        
        let isSelected = false;
        
        if (currentQuestion.multiSelect) {
            // For multi-select questions, check if at least one option is selected
            isSelected = quizState.answers[currentQuestion.id] && quizState.answers[currentQuestion.id].length > 0;
        } else {
            // For single-select questions, check if any option is selected
            isSelected = quizState.answers[currentQuestion.id] !== undefined;
        }
        
        nextButton.disabled = !isSelected;
    }

    /**
     * Filter questions based on conditionals and user answers
     * 
     * @returns {Array} Filtered questions
     */
    function getFilteredQuestions() {
        return quizConfig.questions.filter(question => {
            // Check if question has conditional
            if (question.conditional) {
                const { questionId, showWhen } = question.conditional;
                const answer = quizState.answers[questionId];
                
                // If no answer yet for the conditional question, skip this question
                if (!answer) {
                    return false;
                }
                
                // For multi-select questions
                if (Array.isArray(answer)) {
                    return answer.some(a => showWhen.includes(a));
                }
                
                // For single-select questions
                return showWhen.includes(answer);
            }
            
            // No conditional, include question
            return true;
        });
    }

    /**
     * Submit quiz answers and show results
     */
    function submitQuiz() {
        // Show loader
        const loader = document.getElementById('eridehero-quiz-loader');
        if (loader) {
            loader.style.display = 'flex';
        }
        
        // Hide questions container
        const questionsContainer = document.getElementById('eridehero-quiz-questions-container');
        if (questionsContainer) {
            questionsContainer.style.display = 'none';
        }
        
        // Submit answers to server using fetch API
        fetch(ERideHeroQuiz.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'get_quiz_results',
                nonce: ERideHeroQuiz.nonce,
                quiz_answers: JSON.stringify(quizState.answers)
            })
        })
        .then(response => response.json())
        .then(data => {
            // Hide loader
            if (loader) {
                loader.style.display = 'none';
            }
            
            if (data.success) {
                // Store results in quiz state
                quizState.results = data.data.recommendations;
                
                // Show results
                showResults(data.data.recommendations);
            } else {
                // Show error message
                alert('An error occurred. Please try again.');
                
                // Reset to intro
                const introScreen = document.getElementById('eridehero-quiz-intro');
                if (introScreen) {
                    introScreen.style.display = 'block';
                    fadeIn(introScreen);
                }
            }
        })
        .catch(error => {
            // Hide loader
            if (loader) {
                loader.style.display = 'none';
            }
            
            // Show error message
            alert('An error occurred. Please try again.');
            
            // Reset to intro
            const introScreen = document.getElementById('eridehero-quiz-intro');
            if (introScreen) {
                introScreen.style.display = 'block';
                fadeIn(introScreen);
            }
        });
    }

    /**
     * Show results
     * 
     * @param {Array} recommendations Scooter recommendations
     */
    function showResults(recommendations) {
        const resultsContainer = document.getElementById('eridehero-quiz-results');
        const scooterResults = document.getElementById('eridehero-scooter-results');
        
        if (!resultsContainer || !scooterResults) {
            return;
        }
        
        // Clear previous results
        scooterResults.innerHTML = '';
        
        // Make container visible
        resultsContainer.style.display = 'block';
        
        // Build results HTML
        recommendations.forEach((scooter, index) => {
            const scooterResult = document.createElement('div');
            scooterResult.className = 'eridehero-scooter-result';
            
            // Add result animation with delay based on index
            setTimeout(() => {
                scooterResult.classList.add('result-animation');
            }, index * 200);
            
            // Match percentage
            const matchPercent = document.createElement('div');
            matchPercent.className = 'eridehero-match-percent';
            
            const matchBar = document.createElement('div');
            matchBar.className = 'eridehero-match-bar';
            
            const matchFill = document.createElement('div');
            matchFill.className = 'eridehero-match-fill';
            
            // Animate the fill width after a delay
            setTimeout(() => {
                matchFill.style.width = `${scooter.match_percentage}%`;
            }, index * 200 + 300);
            
            const matchNumber = document.createElement('div');
            matchNumber.className = 'eridehero-match-number';
            matchNumber.textContent = `${scooter.match_percentage}% Match`;
            
            matchBar.appendChild(matchFill);
            matchPercent.appendChild(matchBar);
            matchPercent.appendChild(matchNumber);
            
            // Scooter header
            const scooterHeader = document.createElement('div');
            scooterHeader.className = 'eridehero-scooter-header';
            
            // Scooter image
            const scooterImage = document.createElement('img');
            scooterImage.className = 'eridehero-scooter-image';
            const imgSrc = scooter.image.startsWith('http') ? scooter.image : `${ERideHeroQuiz.pluginurl}escooter-quiz/img/${scooter.image}`;
            scooterImage.src = imgSrc;
            scooterImage.alt = scooter.name;
            
            // Scooter title
            const scooterTitle = document.createElement('div');
            scooterTitle.className = 'eridehero-scooter-title';
            
            const scooterName = document.createElement('div');
            scooterName.className = 'eridehero-scooter-name';
            scooterName.textContent = scooter.name;
            
            const scooterPrice = document.createElement('div');
            scooterPrice.className = 'eridehero-scooter-price';
            scooterPrice.textContent = `$${scooter.price.toFixed(2)}`;
            
            const scooterTagline = document.createElement('div');
            scooterTagline.className = 'eridehero-scooter-tagline';
            scooterTagline.textContent = scooter.tagline;
            
            scooterTitle.appendChild(scooterName);
            scooterTitle.appendChild(scooterPrice);
            scooterTitle.appendChild(scooterTagline);
            scooterHeader.appendChild(scooterImage);
            scooterHeader.appendChild(scooterTitle);
            
            // Scooter content
            const scooterContent = document.createElement('div');
            scooterContent.className = 'eridehero-scooter-content';
            
            // Specs grid
            const specsGrid = document.createElement('div');
            specsGrid.className = 'eridehero-specs-grid';
            
            // Speed spec
            const speedSpec = document.createElement('div');
            speedSpec.className = 'eridehero-spec-item';
            
            const speedValue = document.createElement('div');
            speedValue.className = 'eridehero-spec-value';
            speedValue.textContent = `${scooter.speed} MPH`;
            
            const speedLabel = document.createElement('div');
            speedLabel.className = 'eridehero-spec-label';
            speedLabel.textContent = 'Top Speed';
            
            speedSpec.appendChild(speedValue);
            speedSpec.appendChild(speedLabel);
            
            // Range spec
            const rangeSpec = document.createElement('div');
            rangeSpec.className = 'eridehero-spec-item';
            
            const rangeValue = document.createElement('div');
            rangeValue.className = 'eridehero-spec-value';
            rangeValue.textContent = `${scooter.range} miles`;
            
            const rangeLabel = document.createElement('div');
            rangeLabel.className = 'eridehero-spec-label';
            rangeLabel.textContent = 'Range';
            
            rangeSpec.appendChild(rangeValue);
            rangeSpec.appendChild(rangeLabel);
            
            // Weight spec
            const weightSpec = document.createElement('div');
            weightSpec.className = 'eridehero-spec-item';
            
            const weightValue = document.createElement('div');
            weightValue.className = 'eridehero-spec-value';
            weightValue.textContent = `${scooter.weight} lbs`;
            
            const weightLabel = document.createElement('div');
            weightLabel.className = 'eridehero-spec-label';
            weightLabel.textContent = 'Weight';
            
            weightSpec.appendChild(weightValue);
            weightSpec.appendChild(weightLabel);
            
            // Add specs to grid
            specsGrid.appendChild(speedSpec);
            specsGrid.appendChild(rangeSpec);
            specsGrid.appendChild(weightSpec);
            
            // Features list
            const featuresList = document.createElement('ul');
            featuresList.className = 'eridehero-features-list';
            
            // Add features
            scooter.features.forEach(feature => {
                const featureItem = document.createElement('li');
                featureItem.className = 'eridehero-feature-item';
                
                const featureIcon = document.createElement('span');
                featureIcon.className = 'eridehero-feature-icon';
                featureIcon.textContent = '✓';
                
                const featureText = document.createElement('span');
                featureText.className = 'eridehero-feature-text';
                featureText.textContent = feature;
                
                featureItem.appendChild(featureIcon);
                featureItem.appendChild(featureText);
                featuresList.appendChild(featureItem);
            });
            
            // CTA buttons
            const scooterActions = document.createElement('div');
            scooterActions.className = 'eridehero-scooter-actions';
            
            const priceButton = document.createElement('a');
            priceButton.className = 'eridehero-scooter-btn eridehero-scooter-btn-primary';
            priceButton.href = scooter.url;
            priceButton.textContent = 'Check Current Price';
            priceButton.target = '_blank';
            
            const reviewButton = document.createElement('a');
            reviewButton.className = 'eridehero-scooter-btn eridehero-scooter-btn-secondary';
            reviewButton.href = scooter.url;
            reviewButton.textContent = 'See Full Review';
            reviewButton.target = '_blank';
            
            scooterActions.appendChild(priceButton);
            scooterActions.appendChild(reviewButton);
            
            // Combine all elements
            scooterContent.appendChild(specsGrid);
            scooterContent.appendChild(featuresList);
            scooterContent.appendChild(scooterActions);
            
            scooterResult.appendChild(matchPercent);
            scooterResult.appendChild(scooterHeader);
            scooterResult.appendChild(scooterContent);
            
            scooterResults.appendChild(scooterResult);
        });
        
        // Animate the results container in
        fadeIn(resultsContainer);
    }

    /**
     * Validate email format
     * 
     * @param {string} email Email to validate
     * @returns {boolean} True if valid
     */
    function isValidEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }
    
    /**
     * Fade in element
     * 
     * @param {HTMLElement} element Element to fade in
     * @param {number} duration Duration in milliseconds
     */
    function fadeIn(element, duration = 300) {
        if (!element) return;
        
        element.style.opacity = 0;
        element.style.display = 'block';
        
        let start = null;
        
        function animate(timestamp) {
            if (!start) start = timestamp;
            const progress = timestamp - start;
            
            element.style.opacity = Math.min(progress / duration, 1);
            
            if (progress < duration) {
                window.requestAnimationFrame(animate);
            }
        }
        
        window.requestAnimationFrame(animate);
    }
    
    /**
     * Fade out element
     * 
     * @param {HTMLElement} element Element to fade out
     * @param {Function} callback Callback function after fade out
     * @param {number} duration Duration in milliseconds
     */
    function fadeOut(element, callback = null, duration = 300) {
        if (!element) {
            if (callback) callback();
            return;
        }
        
        let start = null;
        const initialOpacity = parseFloat(window.getComputedStyle(element).opacity);
        
        function animate(timestamp) {
            if (!start) start = timestamp;
            const progress = timestamp - start;
            
            element.style.opacity = Math.max(initialOpacity - (progress / duration), 0);
            
            if (progress < duration) {
                window.requestAnimationFrame(animate);
            } else {
                if (callback) callback();
            }
        }
        
        window.requestAnimationFrame(animate);
    }

    /**
     * Get a formatted SVG icon from the name
     * 
     * @param {string} name Icon name
     * @returns {string} SVG HTML
     */
    function getIcon(name) {
        // Library of SVG icons
        const icons = {
            check: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>',
            arrow: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>'
        };
        
        return icons[name] || '';
    }

    /**
     * Add CSS animations to the page
     */
    function addAnimationStyles() {
        // Create style element
        const style = document.createElement('style');
        style.textContent = `
            /* Option selection animations */
            .select-animation {
                animation: selectPulse 0.3s ease-out;
            }
            
            .deselect-animation {
                animation: deselectPulse 0.3s ease-out;
            }
            
            @keyframes selectPulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
            
            @keyframes deselectPulse {
                0% { transform: scale(1); }
                50% { transform: scale(0.95); }
                100% { transform: scale(1); }
            }
            
            /* Results animations */
            .result-animation {
                animation: slideInUp 0.5s ease-out forwards;
                opacity: 0;
                transform: translateY(20px);
            }
            
            @keyframes slideInUp {
                0% { opacity: 0; transform: translateY(20px); }
                100% { opacity: 1; transform: translateY(0); }
            }
            
            /* Match bar animation */
            .eridehero-match-fill {
                transition: width 1s ease-out;
                width: 0%;
            }
            
            /* Button hover effects */
            .eridehero-quiz-btn, .eridehero-scooter-btn {
                transition: all 0.2s ease;
            }
            
            .eridehero-quiz-btn:hover:not(:disabled), .eridehero-scooter-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            }
            
            .eridehero-quiz-btn:active:not(:disabled), .eridehero-scooter-btn:active {
                transform: translateY(0);
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            
            /* Progress bar animation */
            .eridehero-quiz-progress-bar {
                transition: width 0.4s ease-in-out;
            }
            
            /* Submit button animation */
            .submit-button {
                animation: pulseGlow 1.5s infinite alternate;
            }
            
            @keyframes pulseGlow {
                0% { box-shadow: 0 0 0 rgba(108, 92, 231, 0.4); }
                100% { box-shadow: 0 0 10px rgba(108, 92, 231, 0.7); }
            }
        `;
        
        // Add to document head
        document.head.appendChild(style);
    }
    
    // Add animation styles on load
    addAnimationStyles();
})();