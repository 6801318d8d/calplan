<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AutoSchedule\Tests\Unit;

use OCA\AutoSchedule\Model\Task;
use OCA\AutoSchedule\Service\TaskLimits;
use PHPUnit\Framework\TestCase;

class TaskLimitsTest extends TestCase {

	public function testAncestorsAreResolvedNearestFirst(): void {
		$ancestors = [];
		foreach (TaskLimits::resolve([
			new Task(id: 'COURSE', title: 'Course'),
			new Task(id: 'MODULE', title: 'Module', parentUid: 'COURSE'),
			new Task(id: 'LESSON', title: 'Lesson', parentUid: 'MODULE'),
		]) as $task) {
			$ancestors[$task->id] = $task->ancestors;
		}
		$this->assertSame(['COURSE' => [], 'MODULE' => ['COURSE'], 'LESSON' => ['MODULE', 'COURSE']], $ancestors);
	}

	public function testTreeOrderAndDeadlineComeFromTheTop(): void {
		$due = new \DateTimeImmutable('2026-11-15 10:00:00', new \DateTimeZone('UTC'));
		$out = [];
		foreach (TaskLimits::resolve([
			new Task(id: 'C', title: 'Course', due: $due, priority: 1),
			// Manual order (sortOrder) wins over creation and due dates.
			new Task(id: 'M2', title: 'Module 2', parentUid: 'C', sortOrder: 20),
			new Task(id: 'M1', title: 'Module 1', parentUid: 'C', sortOrder: 10),
			// Same sort order: the title breaks the tie; their own due dates are ignored.
			new Task(id: 'M1B', title: 'Lesson B', parentUid: 'M1', sortOrder: 5, due: new \DateTimeImmutable('2026-09-01')),
			new Task(id: 'M1A', title: 'Lesson A', parentUid: 'M1', sortOrder: 5, due: new \DateTimeImmutable('2026-10-01')),
		]) as $task) {
			$out[$task->id] = [$task->sequence, $task->schedulingDue()?->format('Y-m-d'), $task->rootUid()];
		}
		$this->assertSame([[], '2026-11-15', null], $out['C']);
		$this->assertSame([[1], '2026-11-15', 'C'], $out['M2']);
		$this->assertSame([[0], '2026-11-15', 'C'], $out['M1']);
		$this->assertSame([[0, 0], '2026-11-15', 'C'], $out['M1A']);
		$this->assertSame([[0, 1], '2026-11-15', 'C'], $out['M1B']);
	}

	public function testAParentIsAContainerOnlyWhileASubtaskIsOpen(): void {
		$open = [];
		foreach (TaskLimits::resolve([
			new Task(id: 'BUSY', title: 'Busy parent'),
			new Task(id: 'B1', title: 'Done', parentUid: 'BUSY', status: 'COMPLETED'),
			new Task(id: 'B2', title: 'Open', parentUid: 'BUSY', autoSchedule: true),
			// An open subtask without Auto-schedule does not make its parent a container.
			new Task(id: 'IDLE', title: 'Parent of an unflagged subtask'),
			new Task(id: 'I1', title: 'Open, not flagged', parentUid: 'IDLE'),
			new Task(id: 'DONE', title: 'Finished parent'),
			new Task(id: 'D1', title: 'Done', parentUid: 'DONE', status: 'COMPLETED'),
		]) as $task) {
			$open[$task->id] = $task->hasScheduledSubtasks;
		}
		$this->assertTrue($open['BUSY']);
		$this->assertFalse($open['DONE']);
		$this->assertFalse($open['IDLE']);
		$this->assertFalse($open['B2']);
	}

	public function testParentCycleDoesNotLoop(): void {
		// The links of a cycle are ignored: each task is planned on its own.
		$ancestors = [];
		foreach (TaskLimits::resolve([
			new Task(id: 'A', title: 'A', parentUid: 'B'),
			new Task(id: 'B', title: 'B', parentUid: 'A'),
		]) as $task) {
			$ancestors[$task->id] = $task->ancestors;
		}
		$this->assertSame(['A' => [], 'B' => []], $ancestors);
	}

	public function testTasksOfACycleAreNotContainers(): void {
		$resolved = [];
		foreach (TaskLimits::resolve([
			new Task(id: 'P', title: 'P', autoSchedule: true, parentUid: 'Q'),
			new Task(id: 'Q', title: 'Q', autoSchedule: true, parentUid: 'P'),
			new Task(id: 'SELF', title: 'Self', autoSchedule: true, parentUid: 'SELF'),
			new Task(id: 'C', title: 'Child of the cycle', autoSchedule: true, parentUid: 'P'),
		]) as $task) {
			$resolved[$task->id] = $task;
		}
		$this->assertFalse($resolved['Q']->hasScheduledSubtasks);
		$this->assertFalse($resolved['SELF']->hasScheduledSubtasks);
		$this->assertSame([], $resolved['SELF']->ancestors);
		// A task hanging off the cycle keeps its parent.
		$this->assertTrue($resolved['P']->hasScheduledSubtasks);
		$this->assertSame(['P'], $resolved['C']->ancestors);
	}

	public function testASubtaskAwaitingReviewKeepsItsParentAContainer(): void {
		$resolved = [];
		foreach (TaskLimits::resolve([
			new Task(id: 'COURSE', title: 'Course', autoSchedule: true),
			new Task(id: 'L1', title: 'Lesson', parentUid: 'COURSE', categories: ['calplan-review']),
		]) as $task) {
			$resolved[$task->id] = $task;
		}
		$this->assertTrue($resolved['COURSE']->hasScheduledSubtasks);
	}

	public function testACompletedTopTaskNoLongerRulesItsOpenSubtasks(): void {
		$resolved = [];
		foreach (TaskLimits::resolve([
			new Task(id: 'TOP', title: 'Top', due: new \DateTimeImmutable('2026-09-15'), status: 'COMPLETED'),
			new Task(id: 'MID', title: 'Mid', due: new \DateTimeImmutable('2026-10-27'), parentUid: 'TOP'),
			new Task(id: 'LEAF', title: 'Leaf', autoSchedule: true, parentUid: 'MID'),
		]) as $task) {
			$resolved[$task->id] = $task;
		}
		$this->assertSame(['MID'], $resolved['LEAF']->ancestors);
		$this->assertSame('2026-10-27', $resolved['LEAF']->schedulingDue()?->format('Y-m-d'));
		$this->assertSame(['Mid'], $resolved['LEAF']->ancestorTitles);
	}

	public function testRelationshipsAreResolvedWithTheInheritance(): void {
		$resolved = [];
		foreach (TaskLimits::resolve([
			new Task(id: 'P', title: 'Parent'),
			new Task(id: 'C', title: 'Child', parentUid: 'P'),
		]) as $task) {
			$resolved[$task->id] = $task;
		}
		$this->assertSame(['Child'], $resolved['P']->childTitles);
		$this->assertTrue($resolved['P']->hasChildren);
		$this->assertSame('Parent', $resolved['C']->parentTitle);
	}
}
