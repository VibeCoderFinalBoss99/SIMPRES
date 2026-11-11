package com.simpres.app.ui

import android.animation.ValueAnimator
import android.annotation.SuppressLint
import android.view.MotionEvent
import android.view.View
import android.view.ViewGroup
import android.view.animation.DecelerateInterpolator
import android.view.animation.OvershootInterpolator
import android.widget.EditText
import android.widget.ImageView

/**
 * Small icon animations in the spirit of useAnimations, built only on ViewPropertyAnimator.
 *
 * Kept light on purpose: no extra library, every animation is short and finite (nothing loops forever),
 * a hardware layer is used only while an animation runs, and everything is skipped when the user turned
 * animations off in the system settings (Developer options / Accessibility "Remove animations").
 *
 * Usage in XML: give an icon android:tag="pop", "drop", "ring" or "pulse" and it plays once when the
 * screen opens. Every clickable view also gets a short press feedback automatically (see [IconMotion.apply]).
 */
object IconMotion {

    private fun enabled() = ValueAnimator.areAnimatorsEnabled()

    /** Walks the screen once: press feedback for clickable views, entry animation for tagged icons. */
    fun apply(root: View) {
        if (!enabled()) return
        walk(root)
    }

    private fun walk(view: View) {
        when (view.tag) {
            "pop" -> view.popIn()
            "drop" -> view.dropIn()
            "ring" -> view.ring()
            "pulse" -> view.pulse()
        }
        if (view.isClickable && view !is EditText) {
            view.pressFeedback(if (view is ImageView) 0.86f else 0.97f)
        }
        if (view is ViewGroup) for (i in 0 until view.childCount) walk(view.getChildAt(i))
    }
}

/** Shrinks a little while pressed and springs back on release. Does not consume the touch. */
@SuppressLint("ClickableViewAccessibility")
fun View.pressFeedback(scale: Float = 0.94f) {
    setOnTouchListener { v, event ->
        when (event.actionMasked) {
            MotionEvent.ACTION_DOWN ->
                v.animate().scaleX(scale).scaleY(scale).setDuration(90L)
                    .setInterpolator(DecelerateInterpolator()).start()
            MotionEvent.ACTION_UP, MotionEvent.ACTION_CANCEL ->
                v.animate().scaleX(1f).scaleY(1f).setDuration(220L)
                    .setInterpolator(OvershootInterpolator(3f)).start()
        }
        false
    }
}

/** Status icon (success, sent, offline): grows in with a small overshoot. */
fun View.popIn(delay: Long = 120L) {
    scaleX = 0.4f; scaleY = 0.4f; alpha = 0f
    animate().scaleX(1f).scaleY(1f).alpha(1f)
        .setStartDelay(delay).setDuration(420L)
        .setInterpolator(OvershootInterpolator(2.2f))
        .withLayer().start()
}

/** Location pin: drops from above and settles. */
fun View.dropIn(delay: Long = 150L) {
    val distance = 18f * resources.displayMetrics.density
    translationY = -distance; alpha = 0f
    animate().translationY(0f).alpha(1f)
        .setStartDelay(delay).setDuration(480L)
        .setInterpolator(OvershootInterpolator(2.5f))
        .withLayer().start()
}

/** Notification bell: swings left and right a few times, then stops. */
fun View.ring(delay: Long = 400L) {
    pivotY = 0f
    val angles = floatArrayOf(14f, -12f, 9f, -6f, 3f, 0f)
    var step = 0
    fun next() {
        if (step >= angles.size || !isAttachedToWindow) return
        animate().rotation(angles[step++]).setStartDelay(0L).setDuration(80L)
            .setInterpolator(DecelerateInterpolator()).withEndAction { next() }.start()
    }
    postDelayed({ next() }, delay)
}

/** "You are here" marker: two soft pulses. */
fun View.pulse(times: Int = 2) {
    if (times <= 0) return
    animate().scaleX(1.18f).scaleY(1.18f).setDuration(300L).setStartDelay(0L)
        .setInterpolator(DecelerateInterpolator())
        .withEndAction {
            animate().scaleX(1f).scaleY(1f).setDuration(300L)
                .withEndAction { if (isAttachedToWindow) pulse(times - 1) }.start()
        }.start()
}

/** Active bottom-nav icon: a quick bounce when its tab opens. */
fun View.bounce() {
    if (!ValueAnimator.areAnimatorsEnabled()) return
    scaleX = 0.7f; scaleY = 0.7f
    animate().scaleX(1f).scaleY(1f).setDuration(320L)
        .setInterpolator(OvershootInterpolator(3f)).start()
}
