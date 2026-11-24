package com.simpres.app.ui.auth

import android.animation.Animator
import android.animation.AnimatorListenerAdapter
import android.animation.ValueAnimator
import android.content.res.ColorStateList
import android.text.method.HideReturnsTransformationMethod
import android.view.LayoutInflater
import android.view.ViewGroup
import android.widget.Button
import android.widget.TextView
import androidx.annotation.RawRes
import androidx.annotation.StringRes
import androidx.core.content.ContextCompat
import androidx.core.os.bundleOf
import androidx.recyclerview.widget.RecyclerView
import androidx.viewpager2.widget.ViewPager2
import com.airbnb.lottie.LottieAnimationView
import com.simpres.app.data.ApiResult
import com.simpres.app.data.Session
import com.simpres.app.data.call
import android.text.method.PasswordTransformationMethod
import android.view.View
import android.widget.EditText
import com.simpres.app.R
import com.simpres.app.ui.BaseFragment
import com.simpres.app.ui.onClick

/** Short pause after the intro animation so the app name can be read. */
private const val SPLASH_HOLD_MS = 600L
/** Used when system animations are off: no intro, just a brief splash. */
private const val SPLASH_NO_ANIM_MS = 900L

class SplashFragment : BaseFragment(R.layout.fragment_splash) {
    override fun onReady(view: View) {
        val intro = view.findViewById<LottieAnimationView>(R.id.lottieIntro)
        val texts = listOf(R.id.tvAppName, R.id.vGoldBar, R.id.tvTagline).map { view.findViewById<View>(it) }

        if (!ValueAnimator.areAnimatorsEnabled()) {
            intro.progress = 1f
            view.postDelayed({ goNext() }, SPLASH_NO_ANIM_MS)
            return
        }

        // App name and tagline fade up while the logo animation finishes.
        texts.forEachIndexed { i, text ->
            text.alpha = 0f
            text.translationY = 16 * resources.displayMetrics.density
            text.animate().alpha(1f).translationY(0f)
                .setStartDelay(900L + i * 120L).setDuration(450L).start()
        }
        intro.addAnimatorListener(object : AnimatorListenerAdapter() {
            override fun onAnimationEnd(animation: Animator) {
                view.postDelayed({ goNext() }, SPLASH_HOLD_MS)
            }
        })
        intro.playAnimation()
    }

    private fun goNext() {
        if (!isAdded) return
        // Already signed in: straight to Beranda. Otherwise onboarding, then login.
        if (Session.isLoggedIn) reset(R.id.homeFragment, R.id.splashFragment) else replace(R.id.onboardingFragment)
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

private class OnboardingPage(@RawRes val animation: Int, @StringRes val title: Int, @StringRes val desc: Int)

private val onboardingPages = listOf(
    OnboardingPage(R.raw.onboarding_location, R.string.onboarding_absen_cukup_dari_hp, R.string.onboarding_lokasimu_dicek_otomatis_dengan),
    OnboardingPage(R.raw.onboarding_offline, R.string.onboarding_offline_title, R.string.onboarding_offline_desc),
    OnboardingPage(R.raw.onboarding_leave, R.string.onboarding_leave_title, R.string.onboarding_leave_desc),
)

class OnboardingFragment : BaseFragment(R.layout.fragment_onboarding) {
    override fun onReady(view: View) {
        val pager = view.findViewById<ViewPager2>(R.id.pager)
        val dots = view.findViewById<ViewGroup>(R.id.dots)
        val step = view.findViewById<TextView>(R.id.tvStep)
        val next = view.findViewById<Button>(R.id.btnNext)
        val last = onboardingPages.lastIndex

        pager.adapter = OnboardingAdapter()
        pager.registerOnPageChangeCallback(object : ViewPager2.OnPageChangeCallback() {
            override fun onPageSelected(position: Int) {
                step.text = getString(R.string.onboarding_step, position + 1, onboardingPages.size)
                next.setText(if (position == last) R.string.onboarding_mulai else R.string.onboarding_lanjut)
                updateDots(dots, position)
            }
        })

        view.onClick(R.id.btnSkip) { replace(R.id.loginFragment) }
        next.setOnClickListener {
            if (pager.currentItem == last) replace(R.id.loginFragment) else pager.currentItem += 1
        }
    }

    /** Active dot stretches into a bar; the change is animated by resizing the views. */
    private fun updateDots(dots: ViewGroup, active: Int) {
        val density = resources.displayMetrics.density
        for (i in 0 until dots.childCount) {
            val dot = dots.getChildAt(i)
            val target = ((if (i == active) 24 else 6) * density).toInt()
            ValueAnimator.ofInt(dot.width.takeIf { it > 0 } ?: target, target).apply {
                duration = 220L
                addUpdateListener {
                    dot.layoutParams = dot.layoutParams.apply { width = it.animatedValue as Int }
                }
                start()
            }
            dot.backgroundTintList = ColorStateList.valueOf(
                ContextCompat.getColor(requireContext(), if (i == active) R.color.primary else R.color.dot_off)
            )
        }
    }

    private class OnboardingAdapter : RecyclerView.Adapter<OnboardingAdapter.Holder>() {
        class Holder(view: View) : RecyclerView.ViewHolder(view) {
            val lottie: LottieAnimationView = view.findViewById(R.id.lottie)
            val title: TextView = view.findViewById(R.id.tvTitle)
            val desc: TextView = view.findViewById(R.id.tvDesc)
        }

        override fun onCreateViewHolder(parent: ViewGroup, viewType: Int) =
            Holder(LayoutInflater.from(parent.context).inflate(R.layout.item_onboarding, parent, false))

        override fun getItemCount() = onboardingPages.size

        override fun onBindViewHolder(holder: Holder, position: Int) {
            val page = onboardingPages[position]
            holder.lottie.setAnimation(page.animation)
            holder.title.setText(page.title)
            holder.desc.setText(page.desc)
        }

        // Only the visible page plays, so off-screen animations do not use the CPU.
        override fun onViewAttachedToWindow(holder: Holder) {
            if (ValueAnimator.areAnimatorsEnabled()) holder.lottie.playAnimation()
        }

        override fun onViewDetachedFromWindow(holder: Holder) = holder.lottie.pauseAnimation()
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
        // Sample text from the design is cleared so the real account can be typed.
        username.setText("")
        password.setText("")
        username.hint = getString(R.string.login_email_hint)

        view.onClick(R.id.tvForgot) { go(R.id.forgotFragment) }
        val button = view.findViewById<Button>(R.id.btnLogin)
        button.setOnClickListener {
            val email = username.text.toString().trim()
            val pass = password.text.toString()
            if (email.isEmpty() || pass.isEmpty()) {
                toast(R.string.msg_fill_credentials)
                return@setOnClickListener
            }
            button.isEnabled = false
            button.setText(R.string.login_loading)
            launch {
                when (val r = call { login(mapOf("email" to email, "password" to pass)) }) {
                    is ApiResult.Ok -> {
                        Session.save(r.value.token, r.value.user)
                        com.simpres.app.data.Background.schedule(requireContext())
                        if (r.value.user.role == "admin") toast(getString(R.string.login_admin_note))
                        reset(R.id.homeFragment, R.id.loginFragment)
                        return@launch
                    }
                    is ApiResult.Failed -> alert(r.message)
                    ApiResult.Offline -> alert(getString(R.string.msg_server_unreachable))
                }
                button.isEnabled = true
                button.setText(R.string.login_masuk)
            }
        }
    }
}

/** Asks the server to email a reset link to the account found by NIP or email. */
class ForgotPasswordFragment : BaseFragment(R.layout.fragment_forgot_password) {
    override fun onReady(view: View) {
        val input = view.findViewById<EditText>(R.id.etUsername)
        input.setText("") // sample text from the design
        val button = view.findViewById<Button>(R.id.btnSend)
        button.setOnClickListener {
            val login = input.text.toString().trim()
            if (login.isEmpty()) {
                toast(R.string.forgot_fill)
                return@setOnClickListener
            }
            button.isEnabled = false
            button.setText(R.string.login_loading)
            launch {
                when (val r = call { forgotPassword(mapOf("login" to login)) }) {
                    is ApiResult.Ok -> {
                        replace(R.id.resetSentFragment, bundleOf("email" to r.value.email, "login" to login))
                        return@launch
                    }
                    is ApiResult.Failed -> alert(r.message)
                    ApiResult.Offline -> alert(getString(R.string.msg_server_unreachable))
                }
                button.isEnabled = true
                button.setText(R.string.forgot_password_kirim_tautan_reset)
            }
        }
        view.onClick(R.id.tvBackToLogin) { backTo(R.id.loginFragment) }
    }
}

/** "Cek email kamu": shows the (masked) address the link really went to, with a resend button after 60 s. */
class ResetSentFragment : BaseFragment(R.layout.fragment_reset_sent) {
    override fun onReady(view: View) {
        val email = arguments?.getString("email").orEmpty()
        val login = arguments?.getString("login").orEmpty()
        view.findViewById<TextView>(R.id.tvSentTo).text = getString(R.string.reset_sent_tautan_atur_ulang_kata, email)
        view.onClick(R.id.btnBackToLogin) { backTo(R.id.loginFragment) }
        val resend = view.findViewById<TextView>(R.id.tvResend)
        countdown(resend)
        resend.setOnClickListener {
            resend.isEnabled = false
            launch {
                when (val r = call { forgotPassword(mapOf("login" to login)) }) {
                    is ApiResult.Ok -> { toast(r.value.message); countdown(resend) }
                    is ApiResult.Failed -> { alert(r.message); resend.isEnabled = true }
                    ApiResult.Offline -> { alert(getString(R.string.msg_server_unreachable)); resend.isEnabled = true }
                }
            }
        }
    }

    private fun countdown(resend: TextView) = launch {
        resend.isEnabled = false
        for (s in 60 downTo 1) {
            resend.text = getString(R.string.reset_sent_resend_wait, s)
            kotlinx.coroutines.delay(1000)
        }
        resend.setText(R.string.reset_sent_resend)
        resend.isEnabled = true
    }
}
