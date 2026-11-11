package com.simpres.app.ui

import android.content.Context
import android.content.res.ColorStateList
import android.content.res.Configuration
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.os.Bundle
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.TextView
import android.widget.Toast
import androidx.annotation.IdRes
import androidx.annotation.LayoutRes
import androidx.annotation.StringRes
import androidx.core.content.ContextCompat
import androidx.core.view.WindowCompat
import androidx.fragment.app.Fragment
import androidx.navigation.fragment.findNavController
import androidx.navigation.navOptions
import com.simpres.app.R

/** Base for all screens: wires the shared back/close buttons and the navigation helpers. */
abstract class BaseFragment(@LayoutRes layout: Int) : Fragment(layout) {

    final override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        view.onClick(R.id.btnBack) { findNavController().popBackStack() }
        view.onClick(R.id.btnClose) { backTo(R.id.homeFragment) }
        onReady(view)
        IconMotion.apply(view)
    }

    protected abstract fun onReady(view: View)

    // --- navigation -----------------------------------------------------------------------------

    protected fun go(@IdRes destination: Int) = findNavController().navigate(destination)

    /** Navigate and remove the current screen from the back stack. */
    protected fun replace(@IdRes destination: Int) {
        val current = findNavController().currentDestination?.id ?: return
        findNavController().navigate(destination, null, navOptions { popUpTo(current) { inclusive = true } })
    }

    /** Navigate and clear everything up to (and including) [popTo]. */
    protected fun reset(@IdRes destination: Int, @IdRes popTo: Int) {
        findNavController().navigate(destination, null, navOptions { popUpTo(popTo) { inclusive = true } })
    }

    /** Pop back to an existing screen, e.g. Beranda after a successful check-in. */
    protected fun backTo(@IdRes destination: Int) {
        if (!findNavController().popBackStack(destination, false)) go(destination)
    }

    // --- shared UI ------------------------------------------------------------------------------

    /** Highlights the current tab of view_bottom_nav and wires all of its buttons. */
    protected fun bindBottomNav(root: View, @IdRes current: Int) {
        val tabs = mapOf(
            R.id.navHome to R.id.homeFragment,
            R.id.navHistory to R.id.historyFragment,
            R.id.navSchedule to R.id.scheduleFragment,
            R.id.navProfile to R.id.profileFragment,
        )
        tabs.forEach { (viewId, destination) ->
            val item = root.findViewById<ViewGroup>(viewId)
            val active = destination == current
            val color = ContextCompat.getColor(
                requireContext(), if (active) R.color.primary_on_tint else R.color.ink_faint
            )
            val icon = item.getChildAt(0) as ImageView
            icon.imageTintList = ColorStateList.valueOf(color)
            if (active) icon.bounce()
            (item.getChildAt(1) as TextView).setTextColor(color)
            item.setOnClickListener {
                if (!active) {
                    findNavController().navigate(destination, null, navOptions {
                        popUpTo(R.id.homeFragment)
                        launchSingleTop = true
                    })
                }
            }
        }
        root.onClick(R.id.navFab) { go(R.id.checkInFragment) }
    }

    /** Only one of [ids] is selected at a time (tabs, option chips). The first one starts selected. */
    protected fun singleChoice(root: View, vararg ids: Int, onSelect: (Int) -> Unit = {}) {
        root.findViewById<View>(ids.first()).isSelected = true
        ids.forEach { id ->
            root.findViewById<View>(id).setOnClickListener {
                ids.forEach { other -> root.findViewById<View>(other).isSelected = other == id }
                onSelect(id)
            }
        }
    }

    protected fun toast(@StringRes message: Int) =
        Toast.makeText(requireContext(), message, Toast.LENGTH_SHORT).show()

    protected fun isOnline(): Boolean {
        val manager = requireContext().getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager
        val capabilities = manager.getNetworkCapabilities(manager.activeNetwork) ?: return false
        return capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
    }

    protected fun setStatusBar(@androidx.annotation.ColorRes color: Int, lightIcons: Boolean) {
        val window = requireActivity().window
        window.statusBarColor = ContextCompat.getColor(requireContext(), color)
        WindowCompat.getInsetsController(window, window.decorView).isAppearanceLightStatusBars = lightIcons
    }

    protected fun isNight() =
        resources.configuration.uiMode and Configuration.UI_MODE_NIGHT_MASK == Configuration.UI_MODE_NIGHT_YES
}

fun View.onClick(@IdRes id: Int, action: () -> Unit) {
    findViewById<View>(id)?.setOnClickListener { action() }
}
