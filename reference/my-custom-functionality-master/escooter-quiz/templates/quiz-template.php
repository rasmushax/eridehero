<!-- E-Scooter Quiz Template -->
<div class="eridehero-quiz-container">
    <!-- Intro Section -->
    <div id="eridehero-quiz-intro" class="eridehero-quiz-intro">
        <h2>Find Your Perfect Electric Scooter</h2>
        <p>Based on our testing of 120+ scooters over 8,500+ miles</p>
        
        <img src="<?php echo plugin_dir_url(__FILE__) . 'img/scooter-lineup.jpg'; ?>" alt="Electric Scooter Lineup" class="eridehero-quiz-intro-image">
        
        <p>Answer a few questions to get personalized recommendations from our expert-tested selection. We'll match you with the perfect scooter for your needs, riding style, and budget.</p>
        
        <button id="eridehero-quiz-start" class="eridehero-quiz-btn eridehero-quiz-btn-primary">Start Quiz</button>
    </div>
    
    <!-- Questions Section -->
    <div id="eridehero-quiz-questions-container" style="display: none; position: relative;">
        <!-- Progress Bar -->
        <div class="eridehero-quiz-progress">
            <div id="eridehero-quiz-progress-bar" class="eridehero-quiz-progress-bar"></div>
        </div>
        
        <!-- Questions -->
        <div class="eridehero-quiz-questions">
            <div id="eridehero-quiz-questions"></div>
            
            <!-- Navigation -->
            <div class="eridehero-quiz-nav">
                <button id="eridehero-quiz-back" class="eridehero-quiz-btn eridehero-quiz-btn-secondary" disabled>Back</button>
                <button id="eridehero-quiz-next" class="eridehero-quiz-btn eridehero-quiz-btn-primary" disabled>Next</button>
            </div>
        </div>
        
        <!-- Loader for questions -->
        <div id="eridehero-quiz-loader" class="eridehero-loader">
            <div class="eridehero-spinner"></div>
        </div>
    </div>
    
    <!-- Results Section -->
    <div id="eridehero-quiz-results" class="eridehero-quiz-results" style="display: none;">
        <div class="eridehero-quiz-results-header">
            <h2 class="eridehero-quiz-results-title">Your Perfect Electric Scooters</h2>
            <p class="eridehero-quiz-results-subtitle">Based on your preferences, we've found these top matches from our extensive testing database.</p>
        </div>
        
        <!-- Scooter Results Container -->
        <div id="eridehero-scooter-results">
            <!-- Results will be inserted here dynamically via JavaScript -->
        </div>
        
        <!-- Email Capture -->
        <div class="eridehero-email-capture">
            <p>Want to save these results? We'll email you a copy with more detailed information.</p>
            
            <form id="eridehero-email-form" class="eridehero-email-form">
                <input type="email" id="eridehero-email-input" class="eridehero-email-input" placeholder="Enter your email address" required>
                <button type="submit" class="eridehero-email-btn">Send Results</button>
            </form>
            
            <!-- Success/Error Messages -->
            <div id="eridehero-email-success" class="eridehero-message eridehero-message-success" style="display: none;">
                Results sent successfully! Check your email inbox.
            </div>
            
            <div id="eridehero-email-error" class="eridehero-message eridehero-message-error" style="display: none;">
                An error occurred. Please try again.
            </div>
            
            <!-- Email Loader -->
            <div id="eridehero-email-loader" class="eridehero-loader" style="display: none;">
                <div class="eridehero-spinner"></div>
            </div>
        </div>
        
        <!-- Results Actions -->
        <div class="eridehero-results-actions">
            <button id="eridehero-quiz-retake" class="eridehero-quiz-btn eridehero-quiz-btn-secondary">Retake Quiz</button>
        </div>
    </div>
</div>

<!-- Script to localize quiz variables -->
<script type="text/javascript">
    // This script is included when rendered by WordPress and provides necessary variables to the quiz JavaScript
    var ERideHeroQuiz = {
        ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('eridehero_quiz_nonce'); ?>',
        pluginurl: '<?php echo plugin_dir_url(__FILE__); ?>',
        siteurl: '<?php echo site_url(); ?>'
    };
</script>