package com.simpres.app.ui

import android.annotation.SuppressLint
import android.view.HapticFeedbackConstants
import android.view.MotionEvent
import android.view.View
import android.view.accessibility.AccessibilityNodeInfo
import android.view.animation.OvershootInterpolator

/**
 * "Geser untuk ..." control: the knob follows the finger along the track and confirms when it is
 * released near the end; otherwise it springs back. Screen readers get a normal click action instead.
 */
@SuppressLint("ClickableViewAccessibility")
/** Returns a function that puts the knob back at the start (for example after the user cancels a dialog). */
fun setupSwipeToConfirm(track: View, knob: View, label: View, onConfirm: () -> Unit): () -> Unit {
    var startX = 0f
    var done = false

    fun maxX() = (track.width - track.paddingStart - track.paddingEnd - knob.width).toFloat().coerceAtLeast(1f)

    fun setProgress(x: Float) {
        knob.translationX = x
        label.alpha = 1f - (x / maxX()) * 1.4f
    }

    fun confirm() {
        if (done) return
        done = true
        knob.performHapticFeedback(HapticFeedbackConstants.CONFIRM)
        knob.animate().translationX(maxX()).setDuration(120L)
            .withEndAction { onConfirm() }.start()
        label.animate().alpha(0f).setDuration(120L).start()
    }

    knob.setOnTouchListener { _, event ->
        if (done) return@setOnTouchListener true
        when (event.actionMasked) {
            MotionEvent.ACTION_DOWN -> {
                track.parent?.requestDisallowInterceptTouchEvent(true) // keep the ScrollView from stealing the drag
                startX = event.rawX - knob.translationX
                knob.animate().cancel()
                true
            }
            MotionEvent.ACTION_MOVE -> {
                setProgress((event.rawX - startX).coerceIn(0f, maxX()))
                true
            }
            MotionEvent.ACTION_UP, MotionEvent.ACTION_CANCEL -> {
                track.parent?.requestDisallowInterceptTouchEvent(false)
                if (knob.translationX >= maxX() * 0.8f) {
                    confirm()
                } else {
                    knob.animate().translationX(0f).setDuration(260L)
                        .setInterpolator(OvershootInterpolator(1.5f))
                        .setUpdateListener { label.alpha = 1f - (knob.translationX / maxX()) * 1.4f }
                        .start()
                }
                true
            }
            else -> false
        }
    }

    // Accessibility: TalkBack users activate it with a double tap instead of a drag.
    track.accessibilityDelegate = object : View.AccessibilityDelegate() {
        override fun onInitializeAccessibilityNodeInfo(host: View, info: AccessibilityNodeInfo) {
            super.onInitializeAccessibilityNodeInfo(host, info)
            info.addAction(AccessibilityNodeInfo.AccessibilityAction.ACTION_CLICK)
        }

        override fun performAccessibilityAction(host: View, action: Int, args: android.os.Bundle?): Boolean {
            if (action == AccessibilityNodeInfo.ACTION_CLICK) {
                confirm()
                return true
            }
            return super.performAccessibilityAction(host, action, args)
        }
    }

    return {
        done = false
        knob.animate().translationX(0f).setDuration(220L).start()
        label.animate().alpha(1f).setDuration(220L).start()
    }
}
