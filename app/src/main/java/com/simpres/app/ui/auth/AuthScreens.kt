package com.simpres.app.ui.auth

import android.text.method.HideReturnsTransformationMethod
import android.text.method.PasswordTransformationMethod
import android.view.View
import android.widget.EditText
import com.simpres.app.R
import com.simpres.app.ui.BaseFragment
import com.simpres.app.ui.onClick

private const val SPLASH_DELAY_MS = 1500L

class SplashFragment : BaseFragment(R.layout.fragment_splash) {
    override fun onReady(view: View) {
        view.postDelayed({ if (isAdded) replace(R.id.onboardingFragment) }, SPLASH_DELAY_MS)
    }

    override fun onStart() {
        super.onStart()
        setStatusBar(R.color.primary, lightIcons = false)
    }

    override fun onStop() {
        super.onStop()
        setStatusBar(R.color.bg, lightIcons = !isNight())
    }
}

class OnboardingFragment : BaseFragment(R.layout.fragment_onboarding) {
    override fun onReady(view: View) {
        view.onClick(R.id.btnSkip) { replace(R.id.loginFragment) }
        view.onClick(R.id.btnNext) { replace(R.id.loginFragment) }
    }
}

class LoginFragment : BaseFragment(R.layout.fragment_login) {
    override fun onReady(view: View) {
        val username = view.findViewById<EditText>(R.id.etUsername)
        val password = view.findViewById<EditText>(R.id.etPassword)

        view.onClick(R.id.btnTogglePassword) {
            val hidden = password.transformationMethod is PasswordTransformationMethod
            password.transformationMethod =
                if (hidden) HideReturnsTransformationMethod.getInstance() else PasswordTransformationMethod.getInstance()
            password.setSelection(password.text.length)
        }
        view.onClick(R.id.tvForgot) { go(R.id.forgotFragment) }
        view.onClick(R.id.btnLogin) {
            // TODO: authenticate against the backend; for now any non-empty credentials pass.
            if (username.text.isNullOrBlank() || password.text.isNullOrBlank()) {
                toast(R.string.msg_fill_credentials)
            } else {
                reset(R.id.homeFragment, R.id.loginFragment)
            }
        }
    }
}

class ForgotPasswordFragment : BaseFragment(R.layout.fragment_forgot_password) {
    override fun onReady(view: View) {
        view.onClick(R.id.btnSend) { replace(R.id.resetSentFragment) }
        view.onClick(R.id.tvBackToLogin) { backTo(R.id.loginFragment) }
    }
}

class ResetSentFragment : BaseFragment(R.layout.fragment_reset_sent) {
    override fun onReady(view: View) {
        view.onClick(R.id.btnBackToLogin) { backTo(R.id.loginFragment) }
    }
}
