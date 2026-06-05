(function( $ ) {
	'use strict';

	// Wait for DOM
	$(function() {
		// Check if Firebase is configured and enabled for the frontend
		if ( typeof hwaFirebaseConfig === 'undefined' || ! hwaFirebaseConfig.enabled ) {
			return;
		}

		// Check if we actually have the forms on this page
		const $loginForm = $('#hwa-phone-login-form');
		const $registerForm = $('#hwa-phone-register-form');
		const $linkContainer = $('#hwa-phone-link-container');
		const $unifiedContainer = $('#hwa-unified-auth-container');
		const $checkoutBanner = $('.hwa-checkout-login-banner');

		if ( $loginForm.length === 0 && $registerForm.length === 0 && $linkContainer.length === 0 && $unifiedContainer.length === 0 && $checkoutBanner.length === 0 ) {
			return;
		}

		// Dynamically load Firebase scripts
		const firebaseVersion = '9.23.0'; // Using v9 compat for easier integration in WordPress without a bundler
		
		function loadFirebaseScripts(callback) {
			$.getScript(`https://www.gstatic.com/firebasejs/${firebaseVersion}/firebase-app-compat.js`, function() {
				$.getScript(`https://www.gstatic.com/firebasejs/${firebaseVersion}/firebase-auth-compat.js`, function() {
					callback();
				});
			});
		}

		loadFirebaseScripts(function() {
			// 1. Initialize Firebase
			const firebaseConfig = {
				apiKey: hwaFirebaseConfig.apiKey,
				authDomain: hwaFirebaseConfig.authDomain,
				projectId: hwaFirebaseConfig.projectId,
				appId: hwaFirebaseConfig.appId,
				measurementId: hwaFirebaseConfig.measurementId,
				messagingSenderId: hwaFirebaseConfig.messagingSenderId
			};

			// Prevent re-initialization if another plugin already loaded Firebase
			if (!firebase.apps.length) {
				firebase.initializeApp(firebaseConfig);
			}

			// Keep track of the confirmation result across steps
			let confirmationResult = null;
			let isRecaptchaInitialized = false;

			// Helper to setup reCAPTCHA dynamically based on which form is being used
			function setupRecaptcha( containerId, buttonId ) {
				// Destroy existing if needed (Firebase requires recreating it if switching contexts)
				if ( window.recaptchaVerifier ) {
					window.recaptchaVerifier.clear();
				}
				
				const mode = hwaFirebaseConfig.recaptchaMode === 'visible' ? 'normal' : 'invisible';
				
				window.recaptchaVerifier = new firebase.auth.RecaptchaVerifier(containerId, {
					'size': mode,
					'callback': (response) => {
						// reCAPTCHA solved
					}
				});
				
				// Render it so it's ready
				window.recaptchaVerifier.render();
				isRecaptchaInitialized = true;
			}

			// Helper to display errors
			function showError($errorDiv, message) {
				$errorDiv.text(message).removeClass('hwa-hidden');
			}
			function hideError($errorDiv) {
				$errorDiv.addClass('hwa-hidden').text('');
			}

			// Normalize phone: auto-prepend default country code if user didn't include '+'
			function normalizePhone( raw ) {
				raw = raw.replace(/[\s\-\(\)]/g, ''); // strip spaces, dashes, parens
				if ( raw.charAt(0) !== '+' ) {
					raw = hwaFirebaseConfig.defaultCountryCode + raw;
				}
				return raw;
			}

			// -------------------------------------------------------------------
			// UNIFIED AUTH FLOW (Phase 5)
			// -------------------------------------------------------------------
			if ( $unifiedContainer.length > 0 ) {
				setupRecaptcha('hwa-unified-recaptcha-container', 'hwa-btn-unified-continue');

				let unifiedFlowType = ''; // 'login' or 'register'
				let unifiedIdToken = '';
				let resendTimerInterval = null;

				const $btnContinue = $('#hwa-btn-unified-continue');
				const $btnVerify = $('#hwa-btn-unified-verify');
				const $btnCreate = $('#hwa-btn-unified-create');
				const $btnResend = $('#hwa-resend-otp-btn');
				const $timerDisplay = $('#hwa-resend-timer');

				// Timer Logic
				function startResendTimer() {
					let timeLeft = 60;
					$btnResend.prop('disabled', true).css('text-decoration', 'none');
					$timerDisplay.show().text('00:60');

					if ( resendTimerInterval ) clearInterval(resendTimerInterval);

					resendTimerInterval = setInterval(function() {
						timeLeft--;
						let seconds = timeLeft < 10 ? '0' + timeLeft : timeLeft;
						$timerDisplay.text('00:' + seconds);

						if ( timeLeft <= 0 ) {
							clearInterval(resendTimerInterval);
							$timerDisplay.hide();
							$btnResend.prop('disabled', false).css('text-decoration', 'underline');
						}
					}, 1000);
				}

				// Error handling helper specific to unified flow
				function showUnifiedError(msg) {
					// Using standard JS alert for simplicity in this flow, or could inject error div
					alert(msg); 
				}

				// OTP Input UX (Auto-advance)
				const $otpInputs = $('.hwa-otp-digit');
				$otpInputs.on('keydown', function(e) {
					if ( e.key === 'Backspace' && !$(this).val() ) {
						$(this).prev('.hwa-otp-digit').focus();
					}
				}).on('input', function(e) {
					// Advance focus
					if ( $(this).val() ) {
						$(this).next('.hwa-otp-digit').focus();
					}
					// Check if all 6 are filled to auto-submit
					let fullOtp = '';
					$otpInputs.each(function() { fullOtp += $(this).val(); });
					if ( fullOtp.length === 6 ) {
						$btnVerify.click();
					}
				}).on('paste', function(e) {
					e.preventDefault();
					let pasteData = (e.originalEvent || e).clipboardData.getData('text').trim();
					// Extract digits only
					pasteData = pasteData.replace(/\D/g, '').substring(0, 6);
					if (pasteData) {
						$otpInputs.each(function(index) {
							$(this).val(pasteData[index] || '');
						});
						$otpInputs.last().focus();
						if ( pasteData.length === 6 ) {
							$btnVerify.click();
						}
					}
				});

				// Step 1: Preflight & Send SMS
				$btnContinue.on('click', function(e) {
					e.preventDefault();
					
					const phone = normalizePhone( $('#hwa-unified-phone').val().trim() );
					if ( ! phone ) {
						showUnifiedError(hwaFirebaseConfig.strings.invalid_phone);
						return;
					}

					$btnContinue.prop('disabled', true).text('Loading...');

					// Preflight
					$.ajax({
						url: hwaFirebaseConfig.apiBase + 'auth/preflight',
						method: 'POST',
						data: { phone: phone, _wpnonce: hwaFirebaseConfig.nonce },
						success: function(response) {
							if ( response.success ) {
								unifiedFlowType = response.flow; // 'login' or 'register'

								firebase.auth().signInWithPhoneNumber(phone, window.recaptchaVerifier)
									.then(function(result) {
										confirmationResult = result;
										// Update UI
										$('#hwa-masked-phone-display').text(phone);
										$('#hwa-step-1-phone').removeClass('hwa-active');
										$('#hwa-step-2-otp').addClass('hwa-active');
										startResendTimer();
										$otpInputs.first().focus();
									})
									.catch(function(error) {
										showUnifiedError(error.message);
										$btnContinue.prop('disabled', false).text('CONTINUE');
										window.recaptchaVerifier.render(); // Reset recaptcha
									});
							}
						},
						error: function(xhr) {
							const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : hwaFirebaseConfig.strings.generic_error;
							showUnifiedError(msg);
							$btnContinue.prop('disabled', false).text('CONTINUE');
						}
					});
				});

				// Step 2: Resend OTP
				$btnResend.on('click', function(e) {
					e.preventDefault();
					const phone = normalizePhone( $('#hwa-unified-phone').val().trim() );
					
					$btnResend.prop('disabled', true);
					firebase.auth().signInWithPhoneNumber(phone, window.recaptchaVerifier)
						.then(function(result) {
							confirmationResult = result;
							startResendTimer();
						})
						.catch(function(error) {
							showUnifiedError(error.message);
							$btnResend.prop('disabled', false);
						});
				});

				// Step 2: Verify & Branch
				$btnVerify.on('click', function(e) {
					e.preventDefault();
					
					let otp = '';
					$otpInputs.each(function() { otp += $(this).val(); });

					if ( otp.length < 6 ) {
						showUnifiedError('Please enter a valid 6-digit code.');
						return;
					}

					$btnVerify.prop('disabled', true).text('Verifying...');

					confirmationResult.confirm(otp)
						.then(function(result) {
							return result.user.getIdToken();
						})
						.then(function(idToken) {
							unifiedIdToken = idToken;
							const phone = normalizePhone( $('#hwa-unified-phone').val().trim() );

							if ( unifiedFlowType === 'login' ) {
								// Fast-track: Login immediately
								$.ajax({
									url: hwaFirebaseConfig.apiBase + 'auth/complete',
									method: 'POST',
									data: {
										phone: phone,
										firebase_id_token: unifiedIdToken,
										return_to: new URLSearchParams(window.location.search).get('return_to') || window.location.href,
										_wpnonce: hwaFirebaseConfig.nonce
									},
									success: function(response) {
										if ( response.success && response.redirect_url ) {
											window.location.href = response.redirect_url;
										}
									},
									error: function(xhr) {
										const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : hwaFirebaseConfig.strings.generic_error;
										showUnifiedError(msg);
										$btnVerify.prop('disabled', false).text('VERIFY & PROCEED');
									}
								});
							} else {
								// Register: Need more info, branch to Step 3
								$('#hwa-step-2-otp').removeClass('hwa-active');
								$('#hwa-step-3-profile').addClass('hwa-active');
								$('#hwa-unified-first-name').focus();
							}
						})
						.catch(function(error) {
							showUnifiedError('Invalid verification code. Please try again.');
							$btnVerify.prop('disabled', false).text('VERIFY & PROCEED');
							$otpInputs.val(''); // Clear inputs on error
							$otpInputs.first().focus();
						});
				});

				// Step 3: Complete Profile (Register only)
				$btnCreate.on('click', function(e) {
					e.preventDefault();

					const firstName = $('#hwa-unified-first-name').val().trim();
					const lastName = $('#hwa-unified-last-name').val().trim();
					const email = $('#hwa-unified-email').val().trim();
					const phone = normalizePhone( $('#hwa-unified-phone').val().trim() );

					if ( ! firstName || ! lastName ) {
						showUnifiedError('First and Last name are required.');
						return;
					}

					if ( ! email ) {
						showUnifiedError('Email address is required.');
						return;
					}

					$btnCreate.prop('disabled', true).text('Creating Account...');

					$.ajax({
						url: hwaFirebaseConfig.apiBase + 'auth/complete',
						method: 'POST',
						data: {
							phone: phone,
							firebase_id_token: unifiedIdToken,
							first_name: firstName,
							last_name: lastName,
							email: email,
							return_to: new URLSearchParams(window.location.search).get('return_to') || window.location.href,
							_wpnonce: hwaFirebaseConfig.nonce
						},
						success: function(response) {
							if ( response.success && response.redirect_url ) {
								window.location.href = response.redirect_url;
							}
						},
						error: function(xhr) {
							const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : hwaFirebaseConfig.strings.generic_error;
							showUnifiedError(msg);
							$btnCreate.prop('disabled', false).text('CREATE ACCOUNT');
						}
					});
				});
			}

			// -------------------------------------------------------------------
			// LOGIN FLOW
			// -------------------------------------------------------------------
			if ( $loginForm.length > 0 ) {
				setupRecaptcha('hwa-recaptcha-login-container', 'hwa-btn-send-login-sms');

				const $errorDiv = $('#hwa-phone-login-error');
				
				// Step 1: Preflight and Send SMS
				$('#hwa-btn-send-login-sms').on('click', function(e) {
					e.preventDefault();
					hideError($errorDiv);
					
					const phone = normalizePhone( $('#hwa_login_phone').val().trim() );
					if ( ! phone ) {
						showError($errorDiv, hwaFirebaseConfig.strings.invalid_phone);
						return;
					}

					const $btn = $(this);
					$btn.prop('disabled', true).text('Loading...');

					// Call Preflight Endpoint
					$.ajax({
						url: hwaFirebaseConfig.apiBase + 'login/preflight',
						method: 'POST',
						data: {
							phone: phone,
							_wpnonce: hwaFirebaseConfig.nonce
						},
						success: function(response) {
							// Preflight passed! Now ask Firebase to send SMS
							firebase.auth().signInWithPhoneNumber(phone, window.recaptchaVerifier)
								.then(function(result) {
									confirmationResult = result;
									// Hide Step 1, Show Step 2
									$('#hwa-phone-login-step-1').addClass('hwa-hidden');
									$('#hwa-phone-login-step-2').removeClass('hwa-hidden');
								})
								.catch(function(error) {
									showError($errorDiv, error.message);
									$btn.prop('disabled', false).text('Continue with Phone');
									window.recaptchaVerifier.render(); // Reset recaptcha
								});
						},
						error: function(xhr) {
							const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : hwaFirebaseConfig.strings.generic_error;
							showError($errorDiv, msg);
							$btn.prop('disabled', false).text('Continue with Phone');
						}
					});
				});

				// Step 2: Verify OTP and Login
				$('#hwa-btn-verify-login-otp').on('click', function(e) {
					e.preventDefault();
					hideError($errorDiv);

					const otp = $('#hwa_login_otp').val().trim();
					if ( ! otp || otp.length < 6 ) {
						showError($errorDiv, 'Please enter a valid code.');
						return;
					}

					const $btn = $(this);
					$btn.prop('disabled', true).text('Verifying...');

					// Confirm OTP with Firebase
					confirmationResult.confirm(otp)
						.then(function(result) {
							// Get the ID token
							return result.user.getIdToken();
						})
						.then(function(idToken) {
							// Send ID token to our Complete endpoint
							const phone = normalizePhone( $('#hwa_login_phone').val().trim() );
							
							$.ajax({
								url: hwaFirebaseConfig.apiBase + 'login/complete',
								method: 'POST',
								data: {
									phone: phone,
									firebase_id_token: idToken,
									return_to: window.location.href,
									_wpnonce: hwaFirebaseConfig.nonce
								},
								success: function(response) {
									if ( response.success && response.redirect_url ) {
										window.location.href = response.redirect_url;
									}
								},
								error: function(xhr) {
									const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : hwaFirebaseConfig.strings.generic_error;
									showError($errorDiv, msg);
									$btn.prop('disabled', false).text('Verify and Login');
								}
							});
						})
						.catch(function(error) {
							showError($errorDiv, 'Invalid verification code. Please try again.');
							$btn.prop('disabled', false).text('Verify and Login');
						});
				});
			}

			// -------------------------------------------------------------------
			// REGISTER FLOW
			// -------------------------------------------------------------------
			if ( $registerForm.length > 0 ) {
				// Only setup if login didn't already take the global object, or we are on a different page.
				if ( ! isRecaptchaInitialized ) {
					setupRecaptcha('hwa-recaptcha-register-container', 'hwa-btn-send-register-sms');
				}

				const $errorDiv = $('#hwa-phone-register-error');
				
				// Step 1: Preflight and Send SMS
				$('#hwa-btn-send-register-sms').on('click', function(e) {
					e.preventDefault();
					hideError($errorDiv);
					
					const phone = normalizePhone( $('#hwa_register_phone').val().trim() );
					const firstName = $('#hwa_register_first_name').val().trim();
					const lastName = $('#hwa_register_last_name').val().trim();
					const email = $('#reg_email').length ? $('#reg_email').val().trim() : '';

					if ( ! firstName ) {
						showError($errorDiv, 'Please enter your first name.');
						$('#hwa_register_first_name').focus();
						return;
					}
					if ( ! lastName ) {
						showError($errorDiv, 'Please enter your last name.');
						$('#hwa_register_last_name').focus();
						return;
					}
					if ( ! phone ) {
						showError($errorDiv, hwaFirebaseConfig.strings.invalid_phone);
						$('#hwa_register_phone').focus();
						return;
					}

					const $btn = $(this);
					$btn.prop('disabled', true).text('Loading...');

					// Call Preflight Endpoint
					$.ajax({
						url: hwaFirebaseConfig.apiBase + 'register/preflight',
						method: 'POST',
						data: {
							phone: phone,
							_wpnonce: hwaFirebaseConfig.nonce
						},
						success: function(response) {
							// Preflight passed! Now ask Firebase to send SMS
							firebase.auth().signInWithPhoneNumber(phone, window.recaptchaVerifier)
								.then(function(result) {
									confirmationResult = result;
									// Hide Step 1, Show Step 2
									$('#hwa-phone-register-step-1').addClass('hwa-hidden');
									$('#hwa-phone-register-step-2').removeClass('hwa-hidden');
								})
								.catch(function(error) {
									showError($errorDiv, error.message);
									$btn.prop('disabled', false).text('Continue with Phone');
									window.recaptchaVerifier.render(); // Reset recaptcha
								});
						},
						error: function(xhr) {
							const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : hwaFirebaseConfig.strings.generic_error;
							showError($errorDiv, msg);
							$btn.prop('disabled', false).text('Continue with Phone');
						}
					});
				});

				// Step 2: Verify OTP and Register
				$('#hwa-btn-verify-register-otp').on('click', function(e) {
					e.preventDefault();
					hideError($errorDiv);

					const otp = $('#hwa_register_otp').val().trim();
					if ( ! otp || otp.length < 6 ) {
						showError($errorDiv, 'Please enter a valid code.');
						return;
					}

					const $btn = $(this);
					$btn.prop('disabled', true).text('Verifying...');

					// Confirm OTP with Firebase
					confirmationResult.confirm(otp)
						.then(function(result) {
							return result.user.getIdToken();
						})
						.then(function(idToken) {
							const phone = normalizePhone( $('#hwa_register_phone').val().trim() );
							const firstName = $('#hwa_register_first_name').val().trim();
							const lastName = $('#hwa_register_last_name').val().trim();
							const email = $('#reg_email').length ? $('#reg_email').val().trim() : '';
							
							$.ajax({
								url: hwaFirebaseConfig.apiBase + 'register/complete',
								method: 'POST',
								data: {
									phone: phone,
									firebase_id_token: idToken,
									first_name: firstName,
									last_name: lastName,
									email: email,
									return_to: window.location.href,
									_wpnonce: hwaFirebaseConfig.nonce
								},
								success: function(response) {
									if ( response.success && response.redirect_url ) {
										window.location.href = response.redirect_url;
									}
								},
								error: function(xhr) {
									const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : hwaFirebaseConfig.strings.generic_error;
									showError($errorDiv, msg);
									$btn.prop('disabled', false).text('Verify and Register');
								}
							});
						})
						.catch(function(error) {
							showError($errorDiv, 'Invalid verification code. Please try again.');
							$btn.prop('disabled', false).text('Verify and Register');
						});
				});
			}
			
			// -------------------------------------------------------------------
			// LINK FLOW
			// -------------------------------------------------------------------
			const $linkContainer = $('#hwa-phone-link-container');
			if ( $linkContainer.length > 0 ) {
				// Show form on button click
				$('#hwa-btn-show-link-phone').on('click', function(e) {
					e.preventDefault();
					$linkContainer.removeClass('hwa-hidden');
					$(this).addClass('hwa-hidden'); // hide the trigger button
					setupRecaptcha('hwa-recaptcha-link-container', 'hwa-btn-send-link-sms');
				});

				const $errorDiv = $('#hwa-phone-link-error');
				const $successDiv = $('#hwa-phone-link-success');

				// Step 1: Preflight and Send SMS
				$('#hwa-btn-send-link-sms').on('click', function(e) {
					e.preventDefault();
					hideError($errorDiv);
					hideError($successDiv);
					
					const phone = normalizePhone( $('#hwa_link_phone').val().trim() );
					if ( ! phone ) {
						showError($errorDiv, hwaFirebaseConfig.strings.invalid_phone);
						return;
					}

					const $btn = $(this);
					$btn.prop('disabled', true).text('Loading...');

					$.ajax({
						url: hwaFirebaseConfig.apiBase + 'link/preflight',
						method: 'POST',
						data: {
							phone: phone,
							_wpnonce: hwaFirebaseConfig.nonce
						},
						success: function(response) {
							firebase.auth().signInWithPhoneNumber(phone, window.recaptchaVerifier)
								.then(function(result) {
									confirmationResult = result;
									$('#hwa-phone-link-step-1').addClass('hwa-hidden');
									$('#hwa-phone-link-step-2').removeClass('hwa-hidden');
								})
								.catch(function(error) {
									showError($errorDiv, error.message);
									$btn.prop('disabled', false).text('Send Code');
									window.recaptchaVerifier.render(); // Reset recaptcha
								});
						},
						error: function(xhr) {
							const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : hwaFirebaseConfig.strings.generic_error;
							showError($errorDiv, msg);
							$btn.prop('disabled', false).text('Send Code');
						}
					});
				});

				// Step 2: Verify OTP and Link
				$('#hwa-btn-verify-link-otp').on('click', function(e) {
					e.preventDefault();
					hideError($errorDiv);

					const otp = $('#hwa_link_otp').val().trim();
					if ( ! otp || otp.length < 6 ) {
						showError($errorDiv, 'Please enter a valid code.');
						return;
					}

					const $btn = $(this);
					$btn.prop('disabled', true).text('Verifying...');

					confirmationResult.confirm(otp)
						.then(function(result) {
							return result.user.getIdToken();
						})
						.then(function(idToken) {
							const phone = normalizePhone( $('#hwa_link_phone').val().trim() );
							
							$.ajax({
								url: hwaFirebaseConfig.apiBase + 'link/complete',
								method: 'POST',
								data: {
									phone: phone,
									firebase_id_token: idToken,
									_wpnonce: hwaFirebaseConfig.nonce
								},
								success: function(response) {
									if ( response.success ) {
										$successDiv.text('Phone number successfully linked! Reloading...').removeClass('hwa-hidden');
										$('#hwa-phone-link-step-2').addClass('hwa-hidden');
										setTimeout(() => {
											window.location.reload();
										}, 1500);
									}
								},
								error: function(xhr) {
									const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : hwaFirebaseConfig.strings.generic_error;
									showError($errorDiv, msg);
									$btn.prop('disabled', false).text('Verify & Link');
								}
							});
						})
						.catch(function(error) {
							showError($errorDiv, 'Invalid verification code. Please try again.');
							$btn.prop('disabled', false).text('Verify & Link');
						});
				});
			}

			// -------------------------------------------------------------------
			// UNLINK FLOW
			// -------------------------------------------------------------------
			$('.hwa-btn-unlink').on('click', function(e) {
				e.preventDefault();
				const $btn = $(this);
				const provider = $btn.data('provider');
				
				const isConfirmed = confirm(hwaFirebaseConfig.strings.confirm_unlink || "Are you sure you want to unlink this login method?\n\nIf you don't know your password, you may be locked out of your account.");
				
				if ( ! isConfirmed ) {
					return;
				}

				const originalText = $btn.text();
				$btn.prop('disabled', true).text('Unlinking...');

				$.ajax({
					url: hwaFirebaseConfig.apiRoot + 'unlink',
					method: 'POST',
					data: {
						provider: provider,
						_wpnonce: hwaFirebaseConfig.nonce
					},
					success: function(response) {
						if ( response.success ) {
							alert("Successfully unlinked!");
							window.location.reload();
						}
					},
					error: function(xhr) {
						const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : hwaFirebaseConfig.strings.generic_error;
						alert("Unlink Failed:\n" + msg);
						$btn.prop('disabled', false).text(originalText);
					}
				});
			});

		});

	});

})( jQuery );
