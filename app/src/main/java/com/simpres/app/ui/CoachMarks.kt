package com.simpres.app.ui

import android.animation.ValueAnimator
import android.annotation.SuppressLint
import android.app.Activity
import android.content.Context
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.Paint
import android.graphics.PorterDuff
import android.graphics.PorterDuffXfermode
import android.graphics.RectF
import android.view.LayoutInflater
import android.view.MotionEvent
import android.view.View
import android.view.ViewGroup
import android.widget.Button
import android.widget.FrameLayout
import android.widget.ScrollView
import android.widget.TextView
import androidx.annotation.StringRes
import com.simpres.app.R

/** One step of the in-app guide: the view to highlight and what to say about it. */
class GuideStep(val target: View, @StringRes val title: Int, @StringRes val body: Int)

/**
 * First-run guide (coach marks). Dims the screen, cuts a highlighted hole around one view at a time and
 * shows a small card with an explanation. Shown once per [key]; the flag is stored in SharedPreferences.
 */
object CoachMarks {
    private const val PREFS = "simpres_guide"

    fun isDone(context: Context, key: String) =
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getBoolean(key, false)

    fun reset(context: Context) =
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().clear().apply()

    fun showOnce(activity: Activity, key: String, steps: List<GuideStep>) {
        if (steps.isEmpty() || isDone(activity, key)) return
        val root = activity.findViewById<ViewGroup>(android.R.id.content)
        val overlay = GuideOverlay(activity, steps) {
            activity.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().putBoolean(key, true).apply()
        }
        root.addView(overlay, ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT)
        overlay.start()
    }
}

@SuppressLint("ViewConstructor")
private class GuideOverlay(
    context: Context,
    private val steps: List<GuideStep>,
    private val onFinish: () -> Unit,
) : FrameLayout(context) {

    private val density = resources.displayMetrics.density
    private val scrim = Paint(Paint.ANTI_ALIAS_FLAG).apply { color = Color.argb(185, 8, 14, 28) }
    private val clear = Paint(Paint.ANTI_ALIAS_FLAG).apply { xfermode = PorterDuffXfermode(PorterDuff.Mode.CLEAR) }
    private val ring = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        style = Paint.Style.STROKE
        strokeWidth = 2 * density
        color = Color.WHITE
    }
    private val hole = RectF()
    private val card: View = LayoutInflater.from(context).inflate(R.layout.view_coach_mark, this, false)
    private var index = 0

    init {
        setWillNotDraw(false)
        setLayerType(LAYER_TYPE_HARDWARE, null) // needed for the CLEAR cut-out
        isClickable = true                       // block touches to the screen below while the guide is open
        elevation = 100f
        addView(card, LayoutParams(LayoutParams.MATCH_PARENT, LayoutParams.WRAP_CONTENT).apply {
            val m = (20 * density).toInt(); leftMargin = m; rightMargin = m
        })
        card.findViewById<View>(R.id.btnGuideSkip).setOnClickListener { finish() }
        card.findViewById<Button>(R.id.btnGuideNext).setOnClickListener {
            if (index == steps.lastIndex) finish() else show(index + 1)
        }
    }

    fun start() {
        alpha = 0f
        animate().alpha(1f).setDuration(200L).start()
        post { show(0) }
    }

    private fun show(i: Int) {
        index = i
        val step = steps[i]
        scrollIntoView(step.target)
        post {
            measureHole(step.target)
            card.findViewById<TextView>(R.id.tvGuideStep).text =
                context.getString(R.string.guide_step, i + 1, steps.size)
            card.findViewById<TextView>(R.id.tvGuideTitle).setText(step.title)
            card.findViewById<TextView>(R.id.tvGuideBody).setText(step.body)
            card.findViewById<Button>(R.id.btnGuideNext)
                .setText(if (i == steps.lastIndex) R.string.guide_done else R.string.guide_next)
            placeCard()
            invalidate()
        }
    }

    /** Scrolls the nearest ScrollView so the highlighted view is fully on screen. */
    private fun scrollIntoView(target: View) {
        var node: android.view.ViewParent? = target.parent
        while (node is View) {
            if (node is ScrollView) {
                val r = android.graphics.Rect()
                target.getDrawingRect(r)
                node.offsetDescendantRectToMyCoords(target, r)
                val visibleTop = node.scrollY
                val visibleBottom = visibleTop + node.height
                if (r.top < visibleTop || r.bottom > visibleBottom) {
                    node.scrollTo(0, (r.top - node.height / 3).coerceAtLeast(0))
                }
                return
            }
            node = (node as View).parent
        }
    }

    private fun measureHole(target: View) {
        val t = IntArray(2); val me = IntArray(2)
        target.getLocationInWindow(t); getLocationInWindow(me)
        val pad = 6 * density
        hole.set(
            t[0] - me[0] - pad, t[1] - me[1] - pad,
            t[0] - me[0] + target.width + pad, t[1] - me[1] + target.height + pad,
        )
    }

    /** Card goes below the highlight when there is room, otherwise above it. */
    private fun placeCard() {
        card.measure(
            MeasureSpec.makeMeasureSpec(width - (40 * density).toInt(), MeasureSpec.EXACTLY),
            MeasureSpec.makeMeasureSpec(0, MeasureSpec.UNSPECIFIED),
        )
        val gap = 14 * density
        val below = hole.bottom + gap
        val y = if (below + card.measuredHeight < height - gap) below else hole.top - gap - card.measuredHeight
        val target = y.coerceIn(gap, (height - card.measuredHeight - gap).coerceAtLeast(gap))
        if (ValueAnimator.areAnimatorsEnabled() && card.translationY != 0f) {
            card.animate().translationY(target).setDuration(220L).start()
        } else {
            card.translationY = target
        }
    }

    override fun onDraw(canvas: Canvas) {
        canvas.drawRect(0f, 0f, width.toFloat(), height.toFloat(), scrim)
        if (!hole.isEmpty) {
            val r = 18 * density
            canvas.drawRoundRect(hole, r, r, clear)
            canvas.drawRoundRect(hole, r, r, ring)
        }
    }

    @SuppressLint("ClickableViewAccessibility")
    override fun onTouchEvent(event: MotionEvent) = true

    private fun finish() {
        onFinish()
        animate().alpha(0f).setDuration(180L).withEndAction { (parent as? ViewGroup)?.removeView(this) }.start()
    }
}
